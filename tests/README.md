# Tests

TO test, open *phpunit.xml* in your laravel installations root directory
and add to the `testsuites` section:

	<testsuite name="Application Test Suite">
		<directory>./vendor/flynsarmy/csv-seeder/tests/</directory>
	</testsuite>

Then run `phpunit`