<?php

class CsvTest extends TestCase
{
    /**
     * @before
     */
    public function runDatabaseMigrations()
    {
        // Create our testing DB tables
        $this->artisan('migrate', [
            '--path' => 'vendor/flynsarmy/csv-seeder/tests/migrations',
        ]);

        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:rollback');
        });
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        // Use an in-memory DB
        $this->app['config']->set('database.default', 'csvSeederTest');
        $this->app['config']->set('database.connections.csvSeederTest', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    public function testBOMIsStripped()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder;

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

    public function testMappings()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder;
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

    public function testCanOpenCSV()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder;

        // Test an openable CSV
        $expected = "resource";
        $actual = $seeder->openCSV(__DIR__.'/csvs/users.csv');
        $this->assertInternalType($expected, $actual);

        // Test a non-openable CSV
        $expected = FALSE;
        $actual = $seeder->openCSV(__DIR__.'/csvs/csv_that_does_not_exist.csv');
        $this->assertEquals($expected, $actual);
    }

    public function testImport()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder;
        $seeder->table = 'users';
        $seeder->filename = __DIR__.'/csvs/users.csv';
        $seeder->hashable = '';
        $seeder->run();

        // Make sure the rows imported
        $this->seeInDatabase('users', [
            'id' => 1,
            'first_name' => 'Abe',
            'last_name' => 'Abeson',
            'email' => 'abe.abeson@foo.com',
            'age' => 50,
        ]);
        $this->seeInDatabase('users', [
            'id' => 3,
            'first_name' => 'Charly',
            'last_name' => 'Charlyson',
            'email' => 'charly.charlyson@foo.com',
            'age' => 52,
        ]);
    }

    public function testIgnoredColumnImport()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder;
        $seeder->table = 'users';
        $seeder->filename = __DIR__.'/csvs/users_with_ignored_column.csv';
        $seeder->hashable = '';
        $seeder->run();

        // Make sure the rows imported
        $this->seeInDatabase('users', [
            'id' => 1,
            'first_name' => 'Abe',
            'last_name' => 'Abeson',
            'email' => 'abe.abeson@foo.com',
            'age' => 50,
        ]);
        $this->seeInDatabase('users', [
            'id' => 3,
            'first_name' => 'Charly',
            'last_name' => 'Charlyson',
            'email' => 'charly.charlyson@foo.com',
            'age' => 52,
        ]);
    }

    public function testHash()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder;
        $seeder->table = 'users';
        $seeder->filename = __DIR__.'/csvs/users.csv';

        // Assert unhashed passwords
        $seeder->hashable = '';
        $seeder->run();
        $this->seeInDatabase('users', [
            'id' => 1,
            'password' => 'abeabeson',
        ]);

        // Reset users table
        DB::table('users')->truncate();

        // Assert hashed passwords
        $seeder->hashable = 'password';
        $seeder->run();
        // Row 1 should still be in DB...
        $this->seeInDatabase('users', [
            'id' => 1,
        ]);
        // ... But passwords were hashed
        $this->missingFromDatabase('users', [
            'id' => 1,
            'password' => 'abeabeson',
        ]);
    }

    public function testOffset()
    {
        $seeder = new \Flynsarmy\CsvSeeder\CsvSeeder;
        $seeder->table = 'users';
        $seeder->filename = __DIR__.'/csvs/users.csv';
        $seeder->hashable = '';
        $seeder->offset_rows = 4;
        $seeder->mapping = [
            0 => 'id',
            1 => 'first_name',
            6 => 'age',
        ];
        $seeder->run();

        // Assert offset occurred
        $this->missingFromDatabase('users', [
            'id' => 1,
        ]);

        // Assert mapping worked
        $this->seeInDatabase('users', [
            'id' => 5,
            'first_name' => 'Echo',
            'last_name' => '',
            'age' => 54
        ]);
    }
}