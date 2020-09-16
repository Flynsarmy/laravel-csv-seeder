<?php

namespace Flynsarmy\CsvSeeder\Tests;

class CsvTest extends \Orchestra\Testbench\TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations');

        $this->artisan('migrate');

        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:rollback');
        });
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Use an in-memory DB
        $app['config']->set('database.default', 'csvSeederTest');
        $app['config']->set('database.connections.csvSeederTest', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    /** @test */
    public function it_strips_BOM()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();

        $bomString = chr(239) . chr(187) . chr(191) . "foo";
        $nonBomString = "my non bom string";

        // Test a BOM string
        $expected = "foo";
        $actual = $seeder->stripUtf8Bom($bomString);
        $this->assertEquals($expected, $actual);

        // Test a non BOM string
        $expected = $nonBomString;
        $actual = $seeder->stripUtf8Bom($nonBomString);
        $this->assertEquals($expected, $actual);
    }

    /** @test */
    public function it_removes_unused_hash_columns()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();
        
        // Retain 'password' hashable
        $seeder->hashable = ['password'];
        $mapping = [
            0 => 'id',
            1 => 'password'
        ];
        $expected = ['password'];
        $actual = $seeder->removeUnusedHashColumns($mapping);
        $this->assertEquals($expected, $actual);

        // Remove unused 'password' hashable
        $seeder->hashable = ['password'];
        $mapping = [
            0 => 'id'
        ];
        $expected = [];
        $actual = $seeder->removeUnusedHashColumns($mapping);
        $this->assertEquals($expected, $actual);

        // Remove unused 'foo' hashable but keep 'password'
        $seeder->hashable = ['password', 'foo'];
        $mapping = [
            0 => 'id',
            3 => 'password',
        ];
        $expected = ['password'];
        $actual = $seeder->removeUnusedHashColumns($mapping);
        $this->assertEquals($expected, $actual);
    }

    /** @test */
    public function it_reads_to_mapping_correctly()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();
        $row = [1, 'ignored', 'first', 'last'];

        // Test no skipped columns
        $mapping = [
            0 => 'id',
            1 => 'ignored',
            2 => 'first_name',
            3 => 'last_name',
        ];
        $actual = $seeder->readRow($row, $mapping);
        $expected = [
            'id' => 1,
            'ignored' => 'ignored',
            'first_name' => 'first',
            'last_name' => 'last',
        ];
        $this->assertEquals($expected, $actual);

        // Test a skipped column
        $mapping = [
            0 => 'id',
            2 => 'first_name',
            3 => 'last_name',
        ];
        $actual = $seeder->readRow($row, $mapping);
        $expected = [
            'id' => 1,
            'first_name' => 'first',
            'last_name' => 'last',
        ];
        $this->assertEquals($expected, $actual);

        // Test a non-existant column
        $mapping = [
            0 => 'id',
            2 => 'first_name',
            99 => 'last_name',
        ];
        $actual = $seeder->readRow($row, $mapping);
        $expected = [
            'id' => 1,
            'first_name' => 'first',
            'last_name' => null,
        ];
        $this->assertEquals($expected, $actual);
    }

    /** @test */
    public function it_adds_timestamps()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();
        $row = [1, 'first', 'last'];

        // Test no timetsamps
        $mapping = [
            0 => 'id',
            1 => 'first_name',
        ];
        $actual = $seeder->readRow($row, $mapping);
        $expected = [
            'id' => 1,
            'first_name' => 'first',
        ];
        $this->assertEquals($expected, $actual);

        // Test with timestamps
        $seeder->timestamps = true;
        $seeder->created_at = \Carbon\Carbon::now()->toString();
        $seeder->updated_at = $seeder->created_at;
        $actual = $seeder->readRow($row, $mapping);
        $expected = [
            'id' => 1,
            'first_name' => 'first',
            'created_at' => $seeder->created_at,
            'updated_at' => $seeder->updated_at,
        ];
        $this->assertEquals($expected, $actual);
    }

    /** @test */
    public function it_can_open_CSV()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();

        // Test an openable CSV
        $actual = $seeder->openCSV(__DIR__ . '/csvs/users.csv');
        $this->assertIsResource($actual);

        // Test a non-openable CSV
        $expected = false;
        $actual = $seeder->openCSV(__DIR__ . '/csvs/csv_that_does_not_exist.csv');
        $this->assertEquals($expected, $actual);
    }

    /** @test */
    public function it_creates_mappings()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();
        $seeder->table = 'tests_users';

        // CSV with same columns as DB table
        $row = ['id','first_name','last_name','email','password','address','age'];
        $actual = $seeder->createMappingFromRow($row);
        $expected = ['id','first_name','last_name','email','password','address','age'];
        $this->assertEquals($actual, $expected);

        // CSV with less columns than DB table
        $row = ['id','first_name'];
        $actual = $seeder->createMappingFromRow($row);
        $expected = ['id','first_name'];
        $this->assertEquals($actual, $expected);

        // CSV with more columns as DB table
        $row = ['id','first_name','last_name','email','password','address','age','foo','bar'];
        $actual = $seeder->createMappingFromRow($row);
        $expected = ['id','first_name','last_name','email','password','address','age'];
        $this->assertEquals($actual, $expected);
    }

    /** @test */
    public function it_imports()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();
        $seeder->table = 'tests_users';
        $seeder->filename = __DIR__ . '/csvs/users.csv';
        $seeder->hashable = [];
        $seeder->run();

        // Make sure the rows imported
        $this->assertDatabaseHas('tests_users', [
            'id' => 1,
            'first_name' => 'Abe',
            'last_name' => 'Abeson',
            'email' => 'abe.abeson@foo.com',
            'age' => 50,
            'created_at' => null,
            'updated_at' => null,
        ]);
        $this->assertDatabaseHas('tests_users', [
            'id' => 3,
            'first_name' => 'Charly',
            'last_name' => 'Charlyson',
            'email' => 'charly.charlyson@foo.com',
            'age' => 52,
            'created_at' => null,
            'updated_at' => null,
        ]);
    }

    /** @test */
    public function it_imports_with_timestamps()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();
        $seeder->table = 'tests_users';
        $seeder->filename = __DIR__ . '/csvs/users.csv';
        $seeder->timestamps = true;
        $seeder->run();

        // Make sure timestamps were created
        $this->assertTrue(strlen($seeder->created_at) > 0);
        $this->assertTrue(strlen($seeder->updated_at) > 0);

        // Make sure the rows imported
        $this->assertDatabaseHas('tests_users', [
            'id' => 1,
            'first_name' => 'Abe',
            'last_name' => 'Abeson',
            'email' => 'abe.abeson@foo.com',
            'age' => 50,
            'created_at' => $seeder->created_at,
            'updated_at' => $seeder->updated_at,
        ]);
        $this->assertDatabaseHas('tests_users', [
            'id' => 3,
            'first_name' => 'Charly',
            'last_name' => 'Charlyson',
            'email' => 'charly.charlyson@foo.com',
            'age' => 52,
            'created_at' => $seeder->created_at,
            'updated_at' => $seeder->updated_at,
        ]);
    }

    /** @test */
    public function it_returns_insert_success()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();
        $seeder->table = 'tests_users';

        $expected = true;
        $actual = $seeder->insert([
            'id' => 1,
            'first_name' => 'Abe',
        ]);
        $this->assertEquals($actual, $expected);

        $expected = false;
        $actual = $seeder->insert([
            'id' => 1,
            'non_existent_column' => 'Abe',
        ]);
        $this->assertEquals($actual, $expected);
    }

    /** @test */
    public function it_uses_provided_connection()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();
        $seeder->table = 'tests_users';

        // Default connection works
        $seeder->insert(['id' => 1, 'first_name' => 'Aaron']);
        $this->assertDatabaseHas('tests_users', [
            'id' => 1,
            'first_name' => 'Aaron',
        ]);

        // Reset users table
        \DB::table('tests_users')->truncate();

        // Inserting into a different connection
        $seeder->connection = 'some_connection_that_doesnt_exist';
        $seeder->insert(['id' => 1, 'first_name' => 'Aaron']);
        $this->assertDatabaseMissing('tests_users', [
            'id' => 1,
            'first_name' => 'Aaron',
        ]);
    }

    /** @test */
    public function it_ignores_columns_on_import()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();
        $seeder->table = 'tests_users';
        $seeder->filename = __DIR__ . '/csvs/users_with_ignored_column.csv';
        $seeder->hashable = [];
        $seeder->run();

        // Make sure the rows imported
        $this->assertDatabaseHas('tests_users', [
            'id' => 1,
            'first_name' => 'Abe',
            'last_name' => 'Abeson',
            'email' => 'abe.abeson@foo.com',
            'age' => 50,
        ]);
        $this->assertDatabaseHas('tests_users', [
            'id' => 3,
            'first_name' => 'Charly',
            'last_name' => 'Charlyson',
            'email' => 'charly.charlyson@foo.com',
            'age' => 52,
        ]);
    }

    /** @test */
    public function it_hashes()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();
        $seeder->table = 'tests_users';
        $seeder->filename = __DIR__ . '/csvs/users.csv';

        // Assert unhashed passwords
        $seeder->hashable = [];
        $seeder->run();
        $this->assertDatabaseHas('tests_users', [
            'id' => 1,
            'password' => 'abeabeson',
        ]);

        // Reset users table
        \DB::table('tests_users')->truncate();

        // Assert hashed passwords
        $seeder->hashable = ['password'];
        $seeder->run();
        // Row 1 should still be in DB...
        $this->assertDatabaseHas('tests_users', [
            'id' => 1,
        ]);
        // ... But passwords were hashed
        $this->assertDatabaseMissing('tests_users', [
            'id' => 1,
            'password' => 'abeabeson',
        ]);
    }

    /** @test */
    public function it_offsets()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder();
        $seeder->table = 'tests_users';
        $seeder->filename = __DIR__ . '/csvs/users.csv';
        $seeder->hashable = [];
        $seeder->offset_rows = 4;
        $seeder->mapping = [
            0 => 'id',
            1 => 'first_name',
            6 => 'age',
        ];
        $seeder->run();

        // Assert offset occurred
        $this->assertDatabaseMissing('tests_users', [
            'id' => 1,
        ]);

        // Assert mapping worked
        $this->assertDatabaseHas('tests_users', [
            'id' => 5,
            'first_name' => 'Echo',
            'last_name' => '',
            'email' => '',
            'password' => '',
            'address' => '',
            'age' => 54
        ]);
    }
}
