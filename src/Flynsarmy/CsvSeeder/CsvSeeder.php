<?php namespace Flynsarmy\CsvSeeder;

use Log;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Taken from http://laravelsnippets.com/snippets/seeding-database-with-csv-files-cleanly
 * and modified to include insert chunking
 */
class CsvSeeder extends Seeder {

    /**
     * DB table name
     *
     * @var string
     */
    protected $table;

    /**
     * CSV filename
     *
     * @var string
     */
    protected $filename;

    /**
     * DB field that to be hashed, most likely a password field.
     * If your password has a different name, please overload this
     * variable from our seeder class.
     *
     * @var string
     */
    protected $hashable = 'password';

    /**
     * Generate timestamps
     */
    protected $timestamps = false;

    /**
     * An SQL INSERT query will execute every time this number of rows
     * are read from the CSV. Without this, large INSERTS will silently
     * fail.
     *
     * @var integer
     */
    protected $insert_chunk_size = 50;

    /**
     * CSV delimiter (defaults to ,)
     *
     * @var string
     */
    protected $csv_delimiter = ',';

    /**
     * Run DB seed
     */
    public function run() {
        $this->seedFromCSV($this->filename, $this->csv_delimiter);
    }

    /**
     * Strip UTF-8 BOM characters from the start of a string
     *
     * @param  string $text
     *
     * @return string       String with BOM stripped
     */
    private function strip_utf8_bom($text) {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }

    /**
     * Collect data from a given CSV file and return as array
     *
     * @param $filename
     * @param string $deliminator
     * @return array|bool
     */
    private function seedFromCSV($filename, $deliminator = ",") {
        if (!file_exists($filename) || !is_readable($filename))
            return FALSE;

        // check if file is gzipped
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_mime_type = finfo_file($finfo, $filename);
        finfo_close($finfo);
        $file_is_gzipped = (strcmp($file_mime_type, "application/x-gzip") == 0) ? true : false;

        $header = NULL;
        $row_count = 0;
        $data = array();

        if (($handle = $file_is_gzipped ? popen("gzip -cd " . $filename, 'r') : fopen($filename, 'r')) !== FALSE) {
            Log::info('reading file ' . $filename);
            while (($row = fgetcsv($handle, 0, $deliminator)) !== FALSE) {

                if (!$header) {
                    $header = $row;
                    $header[0] = $this->strip_utf8_bom($header[0]);
                    if ($this->timestamps) {
                        array_push($header, "created_at");
                        array_push($header, "updated_at");
                    }
                } else {
                    // insert only non-empty fields from the csv file
                    $i = 0;
                    $row_values = [];

                    foreach ($header as $key) {
                        if ($i >= count($row)) {
                            if ($this->timestamps) {
                                $row_values['created_at'] = 'NOW()';
                                $row_values['updated_at'] = 'NOW()';
                            }
                        }
                        else {
                            if ($row[$i] === '') {
                                $row_values[$key] = NULL;
                            }
                            else {
                                $row_values[$key] = $row[$i];
                            }
                        }
                        $i++;
                    }

                    if (isset($row_values[$this->hashable])) {
                        $row_values[$this->hashable] = Hash::make($row_values[$this->hashable]);
                    }

                    $data[$row_count] = $row_values;

                    // Chunk size reached, insert
                    if (++$row_count == $this->insert_chunk_size) {
                        $this->run_insert($data);
                        $row_count = 0;
                        //clear the data array explicitly when it was inserted so that nothing is left, otherwise a leftover scenario can cause duplicate inserts
                        $data = array();
                    }
                }
            }

            // Insert any leftover rows
            //check if the data array explicitly if there are any values left to be inserted, if insert them
            if (count($data))
                $this->run_insert($data);

            fclose($handle);
        }

        return $data;
    }

    private function run_insert(array $seedData) {
        try {
            DB::table($this->table)->insert($seedData);
        } catch (\Exception $e) {
            Log::error("CSV insert failed: " . $e->getMessage() . " - CSV " . $this->filename);
        }
    }

}
