<?php

declare(strict_types = 1);

namespace Drupal\Tests\phpexcel\Functional;

use Drupal\Component\Utility\Random;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\phpexcel\PHPExcel;

/**
 * Defines the test case for phpexcel. Test the module API functions.
 *
 * @group phpexcel
 */
class PHPExcelTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $singleworksheetfile;

  /**
   * {@inheritdoc}
   */
  protected $issue1988868File;

  /**
   * {@inheritdoc}
   */
  protected $multipleWorksheetFile;

  /**
   * {@inheritdoc}
   */
  protected $noHeadersFile;

  /**
   * {@inheritdoc}
   */
  protected $dbResultFile;

  /**
   * {@inheritdoc}
   */
  protected $templateFile;

  /**
   * {@inheritdoc}
   */
  protected $directory;

  /**
   * The PHPExcel object.
   *
   * @var Drupal\phpexcel\PHPExcel
   */
  protected $phpexcel;

  /**
   * {@inheritdoc}
   */
  protected $fileSystem;

  /**
   * The entity storage for phpexcel config entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * A user with administration permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'phpexcel'];

  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->phpexcel = \Drupal::service('phpexcel');

    $this->fileSystem = \Drupal::service('file_system');
    $defaultScheme = \Drupal::config('system.file')->get('default_scheme');
    $confPath = \Drupal::getContainer()->getParameter('site.path');

    $this->directory = \Drupal::state()->get('file_' . $defaultScheme . '_path', $confPath . '/files');

    // Make sure the directory is writable.
    $this->assertDirectoryIsWritable($this->directory,
    sprintf("The %s directory exists and is writable.", $this->directory));
  }

  /**
   * Test a simple, single worksheet Excel export.
   */
  public function testSingleWorksheetExport() {
    // Prepare the data.
    $headers = ['Header 1', 'Header 2'];

    $data = [
      ['Data 1.1', 'Data 1.2'],
      ['Data 2.1', 'Data 2.2'],
      ['Data 3.1', 'Data 3.2'],
    ];

    // Create a file path.
    $correct_path = $this->fileSystem->createFilename('phpexcel_test1.xls', $this->directory);

    // The filename will be munged by the export function, so:
    $this->singleworksheetfile = $this->phpexcel->mungeFilename($correct_path);

    // Create a wrong path. Should not be able to export.
    $wrong_path = 'path/to/nowhere/file.xls';

    // Should fail.
    $this->assertEquals(
      PHPEXCEL::PHPEXCEL_ERROR_PATH_NOT_WRITABLE,
      $this->phpexcel->export($headers, $data, $wrong_path),
      'Passed an incorrect path'
    );

    // Should pass.
    $this->assertEquals(
      PHPEXCEL::PHPEXCEL_SUCCESS,
      $this->phpexcel->export($headers, $data, $correct_path),
      sprintf('Exported data to %s', $this->singleworksheetfile)
    );

    // Should pass.
    $this->assertTrue(filesize($this->singleworksheetfile) > 0, 'Filesize should be bigger than 0');

    // Import and check.
    // Import, not keyed by headers.
    $data = $this->phpexcel->import($this->singleworksheetfile, FALSE);

    // Should pass.
    $this->assertTrue(!!$data, 'Import succeeded');

    // Should have 4 rows (3 rows + headers)
    $count = !empty($data[0]) ? count($data[0]) : 0;
    $this->assertTrue($count === 4, sprintf('%s rows, expect 4.', $count));

    // Should only have 2 cells.
    $count = !empty($data[0][0]) ? count($data[0][0]) : 0;
    $this->assertTrue($count === 2, sprintf('%s cells, expect 2.', $count));

    // First row, 1st cell should be 'Header 1'.
    $header = !empty($data[0][0][0]) ? $data[0][0][0] : 'no';
    $this->assertTrue($header === 'Header 1', 'First row, 1st cell data should be "Header 1"');

    // Second row, 1st cell should be 'Data 1.1'.
    $header = !empty($data[0][1][0]) ? $data[0][1][0] : 'no';
    $this->assertTrue($header === 'Data 1.1', 'Second row, 1st cell data should be "Data 1.1"');
  }

  /**
   * A cell with a value of '0' must get exported as such.
   *
   * @see http://drupal.org/node/1988868
   */
  public function testIssue1988868() {
    // Export
    // Prepare the data.
    $headers = ['Header 1', 'Header 2'];

    $data = [
      ['0', 'Data 1.2'],
      ['Data 2.1', '0'],
      ['0', '0'],
    ];

    // Create a file path.
    $correct_path = $this->fileSystem->createFilename('phpexcel_test1.xls', $this->directory);

    // The filename will be munged by the export function, so:
    $this->issue1988868File = $this->phpexcel->mungeFilename($correct_path);

    // Should pass.
    $this->assertEquals(
      PHPEXCEL::PHPEXCEL_SUCCESS,
      $this->phpexcel->export($headers, $data, $correct_path),
      sprintf('Exported data to %s', $this->issue1988868File)
    );

    // Should pass.
    $this->assertTrue(filesize($this->issue1988868File) > 0, 'Filesize should be bigger than 0');

    // Import and check.
    // Import, not keyed by headers.
    $data = $this->phpexcel->import($this->issue1988868File, FALSE);

    // Should pass.
    $this->assertTrue(!!$data, 'Import succeeded');

    // Second row, 1st cell should be 'Data 1.1'.
    $cell = $data[0][1][0];
    $this->assertTrue($cell === '0', 'Second row, 1st cell data should be "0"');

    // Second row, 2nd cell should be 'Data 1.1'.
    $cell = $data[0][1][1];
    $this->assertTrue($cell === 'Data 1.2', 'Second row, 2nd cell data should be "Data 1.2"');

    // Third row, 2nd cell should be '0'.
    $cell = $data[0][2][1];
    $this->assertTrue($cell === '0', 'Third row, 2nd cell data should be "0"');

    // Fourth row, 1st cell should be '0'.
    $cell = $data[0][3][0];
    $this->assertTrue($cell === '0', 'Fourth row, 1st cell data should be "0"');

    // Fourth row, 2nd cell should be '0'.
    $cell = $data[0][3][1];
    $this->assertTrue($cell === '0', 'Fourth row, 2nd cell data should be "0"');
  }

  /**
   * Test multiple worksheet Excel export.
   */
  public function testMultipleWorksheetExport() {
    // Export. Prepare data.
    $headers = [
      'Sheet 1' => ['Header 1.1', 'Header 1.2'],
      'Sheet 2' => ['Header 2.1', 'Header 2.2'],
    ];

    $data = [
      'Sheet 1' => [
        ['Data 1.1.1', 'Data 1.1.2'],
        ['Data 1.2.1', 'Data 1.2.2'],
        ['Data 1.3.1', 'Data 1.3.2'],
      ],
      'Sheet 2' => [
        ['Data 2.1.1', 'Data 2.1.2'],
        ['Data 2.2.1', 'Data 2.2.2'],
        ['Data 2.3.1', 'Data 2.3.2'],
      ],
    ];

    // Create a file path.
    $correct_path = $this->fileSystem->createFilename('phpexcel_test2.xls', $this->directory);

    // The filename will be munged by the export function, so:
    $this->multipleWorksheetFile = $this->phpexcel->mungeFilename($correct_path);

    // Should pass.
    $this->assertEquals(
      PHPEXCEL::PHPEXCEL_SUCCESS,
      $this->phpexcel->export($headers, $data, $correct_path),
      sprintf('Exported data to %s', $this->multipleWorksheetFile)
    );

    // Should pass.
    $this->assertTrue(filesize($this->multipleWorksheetFile) > 0, 'Filesize should be bigger than 0');

    // Import and check.
    // Import, keyed by headers.
    $data = $this->phpexcel->import($this->multipleWorksheetFile);

    // Should pass.
    $this->assertTrue(!!$data, 'Import succeeded');

    // Should have 3 rows.
    $count = !empty($data[0]) ? count($data[0]) : 0;
    $this->assertTrue($count === 3, sprintf('%s rows, expect 3', $count));

    // Should only have 2 cells.
    $count = !empty($data[0][0]) ? count($data[0][0]) : 0;
    $this->assertTrue($count === 2, sprintf('%s cells, expect 2', $count));

    // Should be keyed by headers.
    $this->assertTrue(isset($data[0][0]['Header 1.1']), 'Keyed by header ("Header 1.1")');
    $this->assertTrue(isset($data[1][0]['Header 2.2']), 'Keyed by header ("Header 2.2")');

    $header = !empty($data[0][0]['Header 1.1']) ? $data[0][0]['Header 1.1'] : 'no';
    $this->assertTrue($header === 'Data 1.1.1', 'Should be "Data 1.1.1"');

    $header = !empty($data[1][1]['Header 2.2']) ? $data[1][1]['Header 2.2'] : 'no';
    $this->assertTrue($header === 'Data 2.2.2', 'Should be "Data 2.2.2"');

    // Import and check.
    // Import with worksheet names.
    $data = $this->phpexcel->import($this->multipleWorksheetFile, TRUE, TRUE);

    // Should pass.
    $this->assertTrue(!!$data, 'Import succeeded');

    // Should have 3 rows.
    $count = !empty($data['Sheet 1']) ? count($data['Sheet 1']) : 0;
    $this->assertTrue($count === 3, sprintf('%s rows, expect 3', $count));

    // Should be keyed by Worksheet name.
    $this->assertTrue(isset($data['Sheet 1']), sprintf('Imported with Worksheet names.'));
    $this->assertTrue(isset($data['Sheet 2']), sprintf('Imported with Worksheet names.'));

    // Should only have 2 cells.
    $count = !empty($data['Sheet 1'][0]) ? count($data['Sheet 1'][0]) : 0;
    $this->assertTrue($count === 2, sprintf('%s  cells, expect 2', $count));

    // Should be keyed by headers.
    $this->assertTrue(isset($data['Sheet 1'][0]['Header 1.1']), 'Keyed by header ("Header 1.1")');
    $this->assertTrue(isset($data['Sheet 2'][0]['Header 2.2']), 'Keyed by header ("Header 2.2")');

    $header = !empty($data['Sheet 1'][0]['Header 1.1']) ? $data['Sheet 1'][0]['Header 1.1'] : 'no';
    $this->assertTrue($header === 'Data 1.1.1', 'Should be "Data 1.1.1"');

    $header = !empty($data['Sheet 2'][1]['Header 2.2']) ? $data['Sheet 2'][1]['Header 2.2'] : 'no';
    $this->assertTrue($header === 'Data 2.2.2', 'Should be "Data 2.2.2"');
  }

  /**
   * Test "ignore_headers" option.
   */
  public function testIgnoreHeaders() {
    // Export
    // Prepare data.
    $data = [
      0 => [
        ['Data 1.1.1', 'Data 1.1.2'],
        ['Data 1.2.1', 'Data 1.2.2'],
        ['Data 1.3.1', 'Data 1.3.2'],
      ],
      'Sheet 2' => [
        ['Data 2.1.1', 'Data 2.1.2'],
        ['Data 2.2.1', 'Data 2.2.2'],
        ['Data 2.3.1', 'Data 2.3.2'],
      ],
    ];

    // Create a file path.
    $correct_path = $this->fileSystem->createFilename('phpexcel_test3.xls', $this->directory);

    // The filename will be munged by the export function, so:
    $this->noHeadersFile = $this->phpexcel->mungeFilename($correct_path);

    // Should pass.
    $this->assertEquals(
      PHPEXCEL::PHPEXCEL_SUCCESS,
      $this->phpexcel->export(NULL, $data, $correct_path, ['ignore_headers' => TRUE]),
      sprintf('Exported data to %s', $this->noHeadersFile)
    );

    // Should pass.
    $this->assertTrue(filesize($this->noHeadersFile) > 0, 'Filesize should be bigger than 0');

    // Import and check.
    // Import, not keyed by headers.
    $data = $this->phpexcel->import($this->noHeadersFile, FALSE);

    // Should pass.
    $this->assertTrue(!!$data, 'Import succeeded');

    // Should have 3 rows.
    $count = !empty($data[0]) ? count($data[0]) : 0;
    $this->assertTrue($count === 3, sprintf('%s rows, expect 3', $count));

    // Should only have 2 cells.
    $count = !empty($data[0][0]) ? count($data[0][0]) : 0;
    $this->assertTrue($count === 2, sprintf('%s cells, expect 2', $count));

    $header = !empty($data[0][0][0]) ? $data[0][0][0] : 'no';
    $this->assertTrue($header === 'Data 1.1.1', 'Should be "Data 1.1.1"');

    $header = !empty($data[1][1][1]) ? $data[1][1][1] : 'no';
    $this->assertTrue($header === 'Data 2.2.2', 'Should be "Data 2.2.2"');
  }

  /**
   * Test a simple, single worksheet Excel export.
   */
  public function testTemplateExport() {
    // Export
    // Prepare the data.
    $data = [
      ['Data 1.1', 'Data 1.2'],
      ['Data 2.1', 'Data 2.2'],
      ['Data 3.1', 'Data 3.2'],
    ];

    // Options.
    $options = [
      'format' => 'xlsx',
      'template' => \Drupal::service('extension.list.module')->getPath('phpexcel') . '/tests/src/Functional/data/phpexcel.test.template.xlsx',
      'ignore_headers' => TRUE,
    ];

    // Create a file path.
    $correct_path = $this->fileSystem->createFilename('phpexcel_test4.xlsx', $this->directory);

    // The filename will be munged by the export function, so:
    $this->template_file = $this->phpexcel->mungeFilename($correct_path);

    // Should pass.
    $this->assertEquals(
      PHPEXCEL::PHPEXCEL_SUCCESS,
      $this->phpexcel->export([], $data, $correct_path, $options),
      sprintf('Exported data to %s', $this->template_file)
    );

    // Should pass.
    $this->assertTrue(filesize($this->template_file) > 0, 'Filesize should be bigger than 0');

    // Import and check.
    $data = $this->phpexcel->import($this->template_file, FALSE);

    // Should pass.
    $this->assertTrue(!!$data, 'Import succeeded');

    // Should have 3 rows (3 rows and no headers)
    $count = !empty($data[0]) ? count($data[0]) : 0;
    $this->assertTrue($count === 3, sprintf('%s rows, expect 3', $count));

    // First row, 1st cell should be 'Data 1.1'.
    $header = !empty($data[0][0][0]) ? $data[0][0][0] : 'no';
    $this->assertTrue($header === 'Data 1.1', 'First row, 1st cell data should be "Data 1.1", and is ' . $header);

    // Second row, 1st cell should be 'Data 2.1'.
    $header = !empty($data[0][1][0]) ? $data[0][1][0] : 'no';
    $this->assertTrue($header === 'Data 2.1', 'Second row, 1st cell data should be "Data 2.1", and is ' . $header);
  }

  /**
   * Test db_result export.
   */
  public function testDbResultExport() {
    // Export
    // Create 10 nodes.
    for ($i = 10; $i > 0; $i--) {
      $this->createNodePage();
    }

    // Get the db query result.
    $result = \Drupal::database()->select('node_field_data', 'n')
      ->fields('n', ['nid', 'title'])
      ->execute();

    // Create a path.
    $correct_path = $this->fileSystem->createFilename('phpexcel_test5.xlsx', $this->directory);

    // The filename will be munged by the export function, so:
    $this->dbResultFile = $this->phpexcel->mungeFilename($correct_path);

    // Try exporting to Excel 2007.
    $options = [
      'format' => 'xlsx',
      'creator' => 'SimpleTest',
      'title' => 'DBResult',
      'subject' => 'test',
      'description' => 'my description',
    ];

    // Should pass.
    $this->assertEquals(
      PHPEXCEL::PHPEXCEL_SUCCESS,
      $this->phpexcel->exportDbResult($result, $correct_path, $options),
      sprintf('Exported data to %s', $this->dbResultFile)
    );

    // Should pass.
    $this->assertTrue(filesize($this->dbResultFile) > 0, 'Filesize should be bigger than 0');

    // Import and check.
    // Import, cells keyed by headers.
    $data = $this->phpexcel->import($this->dbResultFile);

    // Should pass.
    $this->assertTrue(!!$data, 'Import succeeded');

    // Should have 10 rows.
    $count = count($data[0]);
    $this->assertTrue($count === 10, sprintf('%s rows, expect 10', $count));

    // Should only have 2 cells.
    $count = count($data[0][0]);
    $this->assertTrue($count === 2, sprintf('%s cells, expect 2', $count));

    // Should be keyed by headers (nid & title)
    $this->assertTrue(isset($data[0][0]['nid']), 'Keyed by header (nid)');
    $this->assertTrue(isset($data[0][1]['title']), 'Keyed by header (title)');
  }

  /**
   * Pass cutom methods and arguments on import to the Reader.
   */
  public function testCustomMethodCalls() {
    // Export
    // Prepare data.
    $headers = [
      'Sheet 1' => ['Header 1.1', 'Header 1.2'],
      'Sheet 2' => ['Header 2.1', 'Header 2.2'],
    ];

    $data = [
      'Sheet 1' => [
        ['Data 1.1.1', 'Data 1.1.2'],
        ['Data 1.2.1', 'Data 1.2.2'],
        ['Data 1.3.1', 'Data 1.3.2'],
      ],
      'Sheet 2' => [
        ['Data 2.1.1', 'Data 2.1.2'],
        ['Data 2.2.1', 'Data 2.2.2'],
        ['Data 2.3.1', 'Data 2.3.2'],
      ],
    ];

    // Create a file path.
    $correct_path = $this->fileSystem->createFilename('phpexcel_test2.xls', $this->directory);

    // The filename will be munged by the export function, so:
    $this->multipleWorksheetFile = $this->phpexcel->mungeFilename($correct_path);

    // Should pass.
    $this->assertEquals(
      PHPEXCEL::PHPEXCEL_SUCCESS,
      $this->phpexcel->export($headers, $data, $correct_path),
      sprintf('Exported data to %s', $this->multipleWorksheetFile)
    );

    // Should pass.
    $this->assertTrue(filesize($this->multipleWorksheetFile) > 0, 'Filesize should be bigger than 0');

    // Import and check.
    // Pass a method call to only import a specific worksheet.
    $data = $this->phpexcel->import($this->multipleWorksheetFile, TRUE, TRUE, ['setLoadSheetsOnly' => ['Sheet 1']]);

    // Should pass.
    $this->assertTrue(!!$data, 'Import succeeded');

    // Should have 3 rows.
    $count = !empty($data['Sheet 1']) ? count($data['Sheet 1']) : 0;
    $this->assertTrue($count === 3, sprintf('%s rows, expect 3', $count));

    // Should be empty.
    $count = !empty($data['Sheet 2']) ? count($data['Sheet 2']) : 0;
    $this->assertTrue($count === 0, sprintf('%s rows, expect 0', $count));
  }

  /**
   * {@inheritdoc}
   */
  protected function createNodePage() {
    $random = new Random();

    $node = Node::create(['type' => 'page']);
    $node->title = $random->name(8);
    $node->body = $random->name(20);
    $node->save();
  }

}
