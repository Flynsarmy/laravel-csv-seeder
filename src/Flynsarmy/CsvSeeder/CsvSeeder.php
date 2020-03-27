<?php namespace Flynsarmy\CsvSeeder;

use App;
use Log;
use DB;
use Hash;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Database\Schema;

/**
 * Taken from http://laravelsnippets.com/snippets/seeding-database-with-csv-files-cleanly
 * and modified to include insert chunking
 */
class CsvSeeder extends Seeder
{
    /**
     * DB table name
     *
     * @var string
     */
    public string $table = '';

    /**
     * CSV filename
     *
     * @var string
     */
    public string $filename = '';

    /**
     * DB connection to use. Leave empty for default connection
     */
    public string $connection = '';

    /**
     * DB fields to be hashed before import, For example a password field.
     */
    public array $hashable = ['password'];

    /**
     * An SQL INSERT query will execute every time this number of rows
     * are read from the CSV. Without this, large INSERTS will silently
     * fail.
     */
    public int $insert_chunk_size = 50;

    /**
     * CSV delimiter (defaults to ,)
     */
    public string $csv_delimiter = ',';

    /**
     * Number of rows to skip at the start of the CSV
     */
    public int $offset_rows = 0;

    /**
     * Can be used to tell the import to trim any leading or trailing white space from the column;
     */
    public bool $should_trim = false;

    /**
     * Add created_at and updated_at to rows
     */
    public bool $timestamps = false;
    /**
     * created_at and updated_at values to be added to each row. Only used if
     * $this->timestamps is true
     */
    public string $created_at = '';
    public string $updated_at = '';

    /**
     * The mapping of CSV to DB column. If not specified manually, the first
     * row (after offset_rows) of your CSV will be read as your DB columns.
     *
     * Mappings take the form of csvColNumber => dbColName.
     *
     * IE to read the first, third and fourth columns of your CSV only, use:
     * array(
     *   0 => id,
     *   2 => name,
     *   3 => description,
     * )
     */
    public array $mapping = [];


    /**
     * Run DB seed
     */
    public function run()
    {
        // Cache created_at and updated_at if we need to
        if ($this->timestamps) {
            if (!$this->created_at) {
                $this->created_at = Carbon::now()->toString();
            }
            if (!$this->updated_at) {
                $this->updated_at = Carbon::now()->toString();
            }
        }

        $this->seedFromCSV($this->filename, $this->csv_delimiter);
    }

    /**
     * Strip UTF-8 BOM characters from the start of a string
     *
     * @param  string $text
     * @return string       String with BOM stripped
     */
    public function stripUtf8Bom(string $text): string
    {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);

        return $text;
    }

    /**
     * Opens a CSV file and returns it as a resource
     *
     * @param $filename
     * @return FALSE|resource
     */
    public function openCSV(string $filename)
    {
        if (!file_exists($filename) || !is_readable($filename)) {
            Log::error("CSV insert failed: CSV " . $filename . " does not exist or is not readable.");
            return false;
        }

        // check if file is gzipped
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_mime_type = finfo_file($finfo, $filename);
        finfo_close($finfo);
        $gzipped = strcmp($file_mime_type, "application/x-gzip") == 0;

        $handle = $gzipped ? gzopen($filename, 'r') : fopen($filename, 'r');

        return $handle;
    }

    /**
     * Collect data from a given CSV file and return as array
     *
     * @param string $filename
     * @param string $deliminator
     * @return array
     */
    public function seedFromCSV(string $filename, string $deliminator = ","): array
    {
        $handle = $this->openCSV($filename);

        // CSV doesn't exist or couldn't be read from.
        if ($handle === false) {
            return [];
        }

        $header = null;
        $row_count = 0;
        $data = [];
        $mapping = $this->mapping ?: [];
        $offset = $this->offset_rows;

        if ($mapping) {
            $this->hashable = $this->removeUnusedHashColumns($mapping);
        }

        while (($row = fgetcsv($handle, 0, $deliminator)) !== false) {
            // Offset the specified number of rows

            while ($offset-- > 0) {
                continue 2;
            }

            // No mapping specified - the first row will be used as the mapping
            // ie it's a CSV title row. This row won't be inserted into the DB.
            if (!$mapping) {
                $mapping = $this->createMappingFromRow($row);
                $this->hashable = $this->removeUnusedHashColumns($mapping);
                continue;
            }

            $row = $this->readRow($row, $mapping);

            // insert only non-empty rows from the csv file
            if (!$row) {
                continue;
            }

            $data[$row_count] = $row;

            // Chunk size reached, insert
            if (++$row_count == $this->insert_chunk_size) {
                $this->insert($data);
                $row_count = 0;
                // clear the data array explicitly when it was inserted so
                // that nothing is left, otherwise a leftover scenario can
                // cause duplicate inserts
                $data = [];
            }
        }

        // Insert any leftover rows
        //check if the data array explicitly if there are any values left to be inserted, if insert them
        if (count($data)) {
            $this->insert($data);
        }

        fclose($handle);

        return $data;
    }

    /**
     * Creates a CSV->DB column mapping from the given CSV row.
     *
     * @param array $row
     * @return array
     */
    public function createMappingFromRow(array $row): array
    {
        $mapping = $row;
        $mapping[0] = $this->stripUtf8Bom($mapping[0]);

        // skip csv columns that don't exist in the database
        foreach ($mapping as $index => $fieldname) {
            if (!DB::getSchemaBuilder()->hasColumn($this->table, $fieldname)) {
                if (isset($mapping[$index])) {
                    unset($mapping[$index]);
                }
            }
        }

        return $mapping;
    }

    /**
     * Removes fields from the hashable array that don't exist in our mapping.
     *
     * This function acts as a performance enhancement - we don't want
     * to search for hashable columns on every row imported when we already
     * know they don't exist.
     *
     * @param array $mapping
     * @return void
     */
    public function removeUnusedHashColumns(array $mapping)
    {
        $hashables = $this->hashable;

        foreach ($hashables as $key => $field) {
            if (!in_array($field, $mapping)) {
                unset($hashables[$key]);
            }
        }

        return $hashables;
    }

    /**
     * Read a CSV row into a DB insertable array
     *
     * @param array $row        List of CSV columns
     * @param array $mapping    Array of csvCol => dbCol
     * @return array
     */
    public function readRow(array $row, array $mapping): array
    {
        $row_values = [];

        foreach ($mapping as $csvCol => $dbCol) {
            if (!isset($row[$csvCol]) || $row[$csvCol] === '') {
                $row_values[$dbCol] = null;
            } else {
                $row_values[$dbCol] = $this->should_trim ? trim($row[$csvCol]) : $row[$csvCol];
            }
        }

        if ($this->hashable) {
            foreach ($this->hashable as $columnToHash) {
                if (isset($row_values[$columnToHash])) {
                    $row_values[$columnToHash] = Hash::make($row_values[$columnToHash]);
                }
            }
        }

        if ($this->timestamps) {
            $row_values['created_at'] = $this->created_at;
            $row_values['updated_at'] = $this->updated_at;
        }

        return $row_values;
    }

    /**
     * Seed a given set of data to the DB
     *
     * @param array $seedData
     * @return bool   TRUE on success else FALSE
     */
    public function insert(array $seedData): bool
    {
        try {
            DB::connection($this->connection)->table($this->table)->insert($seedData);
        } catch (\Exception $e) {
            Log::error("CSV insert failed: " . $e->getMessage() . " - CSV " . $this->filename);
            return false;
        }

        return true;
    }
}
