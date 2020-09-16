## CSV Seeder

[![Latest Version on Packagist](https://img.shields.io/packagist/v/flynsarmy/csv-seeder.svg?style=flat-square)](https://packagist.org/packages/flynsarmy/csv-seeder)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
![Build Status](https://github.com/Flynsarmy/laravel-csv-seeder/workflows/CI/badge.svg)
[![Quality Score](https://scrutinizer-ci.com/g/Flynsarmy/laravel-csv-seeder/badges/quality-score.png)](https://scrutinizer-ci.com/g/flynsarmy/laravel-csv-seeder)
[![Total Downloads](https://img.shields.io/packagist/dt/flynsarmy/csv-seeder?style=flat-square)](https://packagist.org/packages/flynsarmy/csv-seeder)
                    

### Seed your database with CSV files

This package allows CSV based seeds.


### Installation

Require this package in your composer.json and run composer update (or run `composer require flynsarmy/csv-seeder:2.*` directly):

**For PHP 7.4+**

```json
"flynsarmy/csv-seeder": "2.0.*"
```

**For older PHP versions**

```json
"flynsarmy/csv-seeder": "1.*"
```

### Usage

Your CSV's header row should match the DB columns you wish to import. IE to import *id* and *name* columns, your CSV should look like:

```csv
id,name
1,Foo
2,Bar
```

Seed classes must extend `Flynsarmy\CsvSeeder\CsvSeeder`, they must define the destination database table and CSV file path, and finally they must call `parent::run()` like so:

```php
use Flynsarmy\CsvSeeder\CsvSeeder;

class StopsTableSeeder extends CsvSeeder {

	public function __construct()
	{
		$this->table = 'your_table';
		$this->filename = base_path().'/database/seeds/csvs/your_csv.csv';
	}

	public function run()
	{
		// Recommended when importing larger CSVs
		DB::disableQueryLog();

		// Uncomment the below to wipe the table clean before populating
		DB::table($this->table)->truncate();

		parent::run();
	}
}
```

Drop your CSV into */database/seeds/csvs/your_csv.csv* or whatever path you specify in your constructor above.

### Configuration

In addition to setting the database table and CSV filename, the following configuration options are available. They can be set in your class constructor:

 - `connection` (string '') Connection to use for inserts. Leave empty for default connection.
 - `insert_chunk_size` (int 500) An SQL insert statement will trigger every `insert_chunk_size` number of rows while reading the CSV
 - `csv_delimiter` (string ,) The CSV field delimiter.
 - `hashable` (array [password]) List of fields to be hashed before import, useful if you are importing users and need their passwords hashed. Uses `Hash::make()`. Note: This is EXTREMELY SLOW. If you have a lot of rows in your CSV your import will take quite a long time.
 - `offset_rows` (int 0) How many rows at the start of the CSV to ignore. Warning: If used, you probably want to set a mapping as your header row in the CSV will be skipped.
 - `mapping` (array []) Associative array of csvCol => dbCol. See examples section for details. If not specified, the first row (after offset) of the CSV will be used as the mapping.
 - `should_trim` (bool false) Whether to trim the data in each cell of the CSV during import.
 - `timestamps` (bool false) Whether or not to add *created_at* and *updated_at* columns on import.
   - `created_at` (string current time in ISO 8601 format) Only used if `timestamps` is `true`
   - `updated_at` (string current time in ISO 8601 format) Only used if `timestamps` is `true`


### Examples 
CSV with pipe delimited values:

```php
public function __construct()
{
	$this->table = 'users';
	$this->csv_delimiter = '|';
	$this->filename = base_path().'/database/seeds/csvs/your_csv.csv';
}
```

Specifying which CSV columns to import:

```php
public function __construct()
{
	$this->table = 'users';
	$this->csv_delimiter = '|';
	$this->filename = base_path().'/database/seeds/csvs/your_csv.csv';
	$this->mapping = [
	    0 => 'first_name',
	    1 => 'last_name',
	    5 => 'age',
	];
}
```

Trimming the whitespace from the imported data:

```php
public function __construct()
{
	$this->table = 'users';
	$this->csv_delimiter = '|';
	$this->filename = base_path().'/database/seeds/csvs/your_csv.csv';
	$this->mapping = [
	    0 => 'first_name',
	    1 => 'last_name',
	    5 => 'age',
	];
	$this->should_trim = true;
}
```

Skipping the CSV header row (Note: A mapping is required if this is done):

```php
public function __construct()
{
	$this->table = 'users';
	$this->csv_delimiter = '|';
	$this->filename = base_path().'/database/seeds/csvs/your_csv.csv';
	$this->offset_rows = 1;
	$this->mapping = [
	    0 => 'first_name',
	    1 => 'last_name',
	    2 => 'password',
	];
	$this->should_trim = true;
}
```

Specifying the DB connection to use:

```php
public function __construct()
{
	$this->table = 'users';
	$this->connection = 'my_connection';
	$this->filename = base_path().'/database/seeds/csvs/your_csv.csv';
}
```

### Migration Guide

#### 2.0

- `$seeder->hashable` is now an `array` of columns rather than a single column name. Wrap your old string value in `[]`.

### License

CsvSeeder is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
