<?php

namespace Drupal\phpexcel;

use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Class containing shortcuts for procedural code.
 *
 * This helpers should only be used in situations where dependencies cannot be
 * injected; e.g., in hook implementations or static methods.
 *
 * Defines the phpexcel api functions that other modules can use.
 */
class PHPExcel {

  use StringTranslationTrait;

  const PHPEXCEL_ERROR_NO_HEADERS = 0;
  const PHPEXCEL_ERROR_NO_DATA = 1;
  const PHPEXCEL_ERROR_PATH_NOT_WRITABLE = 2;
  const PHPEXCEL_ERROR_LIBRARY_NOT_FOUND = 3;
  const PHPEXCEL_ERROR_FILE_NOT_WRITTEN = 4;
  const PHPEXCEL_ERROR_FILE_NOT_READABLE = 5;
  const PHPEXCEL_CACHING_METHOD_UNAVAILABLE = 6;
  const PHPEXCEL_SUCCESS = 10;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The event dispatcher to dispatch the filename sanitize event.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The phpexcelConfig configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $phpexcelConfig;

  /**
   * The config name.
   *
   * @var string
   */
  protected $configName = 'phpexcel.settings';

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The settings for cached.
   *
   * @var array
   */
  protected $cacheSettings;

  /**
   * The StringTranslation handler.
   *
   * @var Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * PHPExcel constructor.
   *
   * @param \Drupal\Core\Logger\LoggerInterface $logger
   *   The logger service the instance should use.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A configuration factory instance.
   * @param Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   Print logs using the translation function.
   */
  public function __construct(LoggerInterface $logger,
  EventDispatcherInterface $event_dispatcher,
  ModuleHandlerInterface $module_handler,
  ConfigFactoryInterface $config_factory,
  TranslationInterface $string_translation) {
    // Log the operation.
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->phpexcelConfig = $config_factory->get('phpexcel.settings');
    $this->stringTranslation = $string_translation;
  }

  /**
   * API function which will generate an XLS file and ave it in $path.
   *
   * @param array $headers
   *   An array containing all headers. If given a two-dimensional array,
   *    each first dimension entry will be on a separate worksheet
   *    ($headers[sheet][header]).
   * @param array $data
   *   A two-dimensional array containing all data ($data[row][column]).
   *    If given a three-dimensional array, each first dimension
   *    entry will be on a separate worksheet ($data[sheet][row][column]).
   * @param string $path
   *   The path where the file must be saved. Must be writable.
   * @param array $options
   *   An array which allows to set some specific options.
   *    Used keys:
   *    - ignore_headers: whether the $headers parameter should be ignored or
   *      not. Defaults to false.
   *    - format: The EXCEL format.
   *      Can be either 'xls', 'xlsx', 'csv', or 'ods'.
   *      Defaults to the extension given in the $path parameter, or 'xls'.
   *    - creator: The name of the creator.
   *    - title: The title.
   *    - subject: The subject.
   *    - description: The description.
   *    - template: A path to a file to use as a template.
   *    - merge_cells: Array with sheets and cell ranges for merge. For example:
   *      [sheet][0]='A1:C1'.
   *    The options array will always be passed to all the hooks. If
   *    developers need specific information for their own hooks, they
   *    can add any data to this array.
   *
   * @return int
   *   PHPEXCEL_SUCCESS on success, PHPEXCEL_ERROR_NO_HEADERS,
   *    PHPEXCEL_ERROR_NO_DATA, PHPEXCEL_ERROR_PATH_NOT_WRITABLE or
   *    PHPEXCEL_ERROR_LIBRARY_NOT_FOUND on error.
   *
   * @see hook_phpexcel_export()
   *
   * @ingroup phpexcel_api
   */
  public function export(array $headers = NULL, array $data = [], $path = '', array $options = NULL) {
    if (empty($headers) && empty($options['ignore_headers'])) {
      $this->logger->error("No header was provided, and the 'ignore_headers' option was
not set to TRUE. Excel export aborted.");

      return self::PHPEXCEL_ERROR_NO_HEADERS;
    }

    // Make sure we have an ignore_headers key to prevent Notices.
    $options['ignore_headers'] = $options['ignore_headers'] ?? empty($headers);

    if (!count($data)) {
      $this->logger->error("No data was provided. Excel export aborted.");

      return self::PHPEXCEL_ERROR_NO_DATA;
    }

    if (!(is_writable($path) || (!file_exists($path) && is_writable(dirname($path))))) {
      $this->logger->error(
        "Path '@path' is not writable. Excel export aborted.",
        ['@path' => $path]
      );

      return self::PHPEXCEL_ERROR_PATH_NOT_WRITABLE;
    }

    if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
      $this->logger->error("Couldn't find the PhpSpreadsheet library. Excel export aborted.");

      return self::PHPEXCEL_ERROR_LIBRARY_NOT_FOUND;
    }

    $path = $this->mungeFilename($path);

    // Determine caching method.
    $cache_method = $this->getCacheSettings();

    // Enable if exist.
    if (!empty($cache_method)) {
      Settings::setCache($cache_method);
    }

    // First, see if the file already exists.
    if (file_exists($path)) {
      $xls = IOFactory::load($path);
    }
    elseif (!empty($options['template'])) {
      // Must we render from a template file ?
      $xls_reader = IOFactory::createReaderForFile($options['template']);

      $xls = $xls_reader->load($options['template']);
    }
    else {
      $xls = new Spreadsheet();
    }

    $this->setProperties($xls->getProperties(), $options);

    // Must we ignore the headers ?
    if (empty($options['ignore_headers'])) {
      $this->setHeaders($xls, $headers, $options);
    }

    $this->setColumns($xls, $data, empty($options['ignore_headers']) ? $headers : NULL, $options);

    // Merge cells.
    if (!empty($options['merge_cells'])) {
      foreach ($options['merge_cells'] as $sheet_name => $merge_cells_list) {
        foreach ($merge_cells_list as $merge_cells) {
          $sheet = $xls->setActiveSheetIndex($sheet_name);
          $style = [
            'alignment' => [
              'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
          ];
          $sheet->getStyle($merge_cells)->applyFromArray($style);
          $xls->getActiveSheet()->mergeCells($merge_cells);
        }
      }
    }

    $format = isset($options['format']) ? mb_strtolower($options['format']) : @end(explode('.', $path));

    switch ($format) {
      case 'xlsx':
        $writer = new Xlsx($xls);
        break;

      case 'csv':
        $writer = new Csv($xls);
        break;

      case 'ods':
        $writer = new Ods($xls);
        break;

      default:
        $writer = new Xls($xls);
    }

    $writer->save($path);
    unset($writer);

    return file_exists($path) ? self::PHPEXCEL_SUCCESS : self::PHPEXCEL_ERROR_FILE_NOT_WRITTEN;
  }

  /**
   * Export a database result to an Excel file.
   *
   * Simple API function which allows to export a db_query() result to an Excel
   * file. The headers will be set to the names of the exported columns.
   *
   * @param \Drupal\Core\Database\StatementInterface $result
   *   The database result object.
   * @param string $path
   *   The path where the file should be saved. Must be writable.
   * @param array $options
   *   An array which allows to set some specific options.
   *
   * @return bool
   *   TRUE on success, FALSE on error. Look into watchdog logs for information
   *    about errors.
   */
  public function exportDbResult(StatementInterface $result = NULL, $path, array $options = []) {
    $data = [];

    while ($row = $result->fetchAssoc()) {
      if (!isset($headers)) {
        $headers = array_keys($row);
      }
      $data[] = array_values($row);
    }

    return $this->export($headers, $data, $path, $options);
  }

  /**
   * Sets the Excel file properties, like creator, title, etc.
   *
   * @see $this->export()
   */
  public function setProperties($properties, $options) {
    if (isset($options['creator'])) {
      $properties->setCreator($options['creator']);
    }
    else {
      $properties->setCreator("\PhpOffice\PhpSpreadsheet\Spreadsheet");
    }

    if (isset($options['title'])) {
      $properties->setTitle($options['title']);
    }

    if (isset($options['subject'])) {
      $properties->setSubject($options['subject']);
    }

    if (isset($options['description'])) {
      $properties->setDescription($options['description']);
    }
  }

  /**
   * Sets the Excel file headers.
   *
   * @see $this->export()
   */
  public function setHeaders($xls, &$headers, $options) {
    if (!is_array(current(array_values($headers)))) {
      $headers = [$headers];
    }

    $this->invoke('export', 'headers', $headers, $xls, $options);

    $sheet_id = 0;
    foreach ($headers as $sheet_name => $sheet_headers) {
      // If the sheet name is just an index, assume to create a string name.
      if (is_numeric($sheet_name)) {
        $sheet_name = $this->t('Worksheet :id', [':id' => ($sheet_id + 1)]);
      }
      // First, attempt to open an existing sheet by the given name.
      if (($sheet = $xls->getSheetByName($sheet_name)) === NULL) {
        if ($sheet_id) {
          $xls->createSheet($sheet_id);
          $sheet = $xls->setActiveSheetIndex($sheet_id);
        }
        else {
          // PhpSpreadsheet always creates one sheet.
          $sheet = $xls->getSheet(0);
        }

        $invalidCharacters = $sheet->getInvalidCharacters();
        $sheet_name = str_replace($invalidCharacters, '', $sheet_name);

        $sheet->setTitle($sheet_name);

        $this->invoke('export', 'new sheet', $sheet_id, $xls, $options);
      }

      for ($i = 0, $len = count($sheet_headers); $i < $len; $i++) {
        $value = trim($sheet_headers[$i]);

        $this->invoke('export', 'pre cell', $value, $sheet, $options, $i, 1);

        $sheet->setCellValueByColumnAndRow($i + 1, 1, $value);

        $this->invoke('export', 'post cell', $value, $sheet, $options, $i, 1);
      }

      $sheet_id++;
    }
  }

  /**
   * Adds the data to the Excel file.
   *
   * @see $this->export()
   */
  public function setColumns($xls, &$data, $headers = NULL, $options = []) {
    if (!is_array(current(current(array_values($data))))) {
      $data = [$data];
    }

    $this->invoke('export', 'data', $data, $xls, $options);

    $sheet_id = 0;
    foreach ($data as $sheet_name => $sheet_data) {
      // If the sheet name is just an index, assume to create a string name.
      if (is_numeric($sheet_name)) {
        $sheet_name = $this->t('Worksheet :id', [':id' => ($sheet_id + 1)]);
      }
      // First, attempt to open an existing sheet by the given name.
      if (($sheet = $xls->getSheetByName($sheet_name)) === NULL) {
        // If the headers are not set, we haven't created any sheets yet.
        // Create them now.
        if (!isset($headers)) {
          if ($sheet_id) {
            $xls->createSheet($sheet_id);
            $sheet = $xls->setActiveSheetIndex($sheet_id);
          }
          else {
            // PhpSpreadsheet always creates one sheet.
            $sheet = $xls->getSheet(0);
          }

          $invalidCharacters = $sheet->getInvalidCharacters();
          $sheet_name = str_replace($invalidCharacters, '', $sheet_name);

          $sheet->setTitle($sheet_name);

          $this->invoke('export', 'new sheet', $sheet_id, $xls, $options);
        }
        else {
          $sheet = $xls->setActiveSheetIndex($sheet_id);
        }
      }

      // Get the highest row of the sheet to calculate the offset
      // so that rows are simply appended rather than overwritten
      // if the file is built in multiple passes.
      $offset = $sheet->getHighestRow() + ($options['ignore_headers'] ? 0 : 1);

      for ($i = 0, $len = count($sheet_data); $i < $len; $i++) {
        for ($j = 0; $j < count($sheet_data[$i]); $j++) {
          $value = $sheet_data[$i][$j] ?? '';

          // We must offset the row count (by 2 if the first row is used by the
          // headers, because PhpSpreadsheet starts the count at 1, not 0).
          $this->invoke('export', 'pre cell', $value, $sheet, $options, $j, $i + $offset);

          $sheet->setCellValueByColumnAndRow($j + 1, $i + $offset, $value);

          $this->invoke('export', 'post cell', $value, $sheet, $options, $j, $i + $offset);
        }
      }

      $sheet_id++;
    }
  }

  /**
   * Import an Excel file.
   *
   * Simple API function that will load an Excel file from $path and parse it
   * as a multidimensional array.
   *
   * @param string $path
   *   The path to the Excel file. Must be readable.
   * @param bool $keyed_by_headers
   *   = TRUE
   *   If TRUE, will key the row array with the header values and will
   *    skip the header row. If FALSE, will contain the headers in the first
   *    row and the rows will be keyed numerically.
   * @param bool $keyed_by_worksheet
   *   = FALSE
   *   If TRUE, will key the data array with the worksheet names. Otherwise, it
   *    will use a numerical key.
   * @param array $custom_calls
   *   = NULL
   *   An associative array of methods and arguments to call on the PHPExcel
   *    Reader object.
   *    For example, if you wish to load only a specific worksheet to save time,
   *    you could use:
   *    @code
   *    $phpexcel = \Drupal::service('phpexcel');
   *    $filepath = 'sites/default/files/phpexcel.test.multi_sheet.xlsx';
   *    $result = $phpexcel->import($filepath, TRUE, TRUE, array(
   *      'setLoadSheetsOnly' => ['Sheet 2'],
   *    ));
   *    @endcode
   *
   * @return array|int
   *   The parsed data as an array on success, PHPEXCEL_ERROR_LIBRARY_NOT_FOUND
   *    or PHPEXCEL_ERROR_FILE_NOT_READABLE on error.
   *
   * @see hook_phpexcel_import()
   *
   * @ingroup phpexcel_api
   */
  public function import($path, $keyed_by_headers = TRUE, $keyed_by_worksheet = FALSE, array $custom_calls = []) {
    if (is_readable($path)) {

      if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        // Determine caching method.
        $cache_method = $this->getCacheSettings();
        // Enable if exist.
        if (!empty($cache_method)) {
          Settings::setCache($cache_method);
        }
        $xls_reader = IOFactory::createReaderForFile($path);

        $custom_calls = [
          'setReadDataOnly' => [TRUE],
        ] + $custom_calls;

        if (!empty($custom_calls)) {
          foreach ($custom_calls as $method => $args) {
            if (method_exists($xls_reader, $method)) {
              call_user_func_array([$xls_reader, $method], $args);
            }
          }
        }

        $xls_data = $xls_reader->load($path);

        $data = [];
        $headers = [];
        $options = [
          'path' => $path,
          'keyed_by_headers' => $keyed_by_headers,
          'keyed_by_worksheet' => $keyed_by_worksheet,
          'custom_calls' => $custom_calls,
        ];
        $i = 0;

        $this->invoke('import', 'full', $xls_data, $xls_reader, $options);

        foreach ($xls_data->getWorksheetIterator() as $worksheet) {
          $j = 0;

          $this->invoke('import', 'sheet', $worksheet, $xls_reader, $options);

          foreach ($worksheet->getRowIterator() as $row) {
            if ($keyed_by_worksheet) {
              $i = $worksheet->getTitle();
            }
            $k = 0;

            $cells = $row->getCellIterator();

            $cells->setIterateOnlyExistingCells(FALSE);

            $this->invoke('import', 'row', $row, $xls_reader, $options);

            foreach ($cells as $cell) {
              $value = $cell->getFormattedValue();
              $value = mb_strlen($value) ? trim($value) : '';

              if (!$j && $keyed_by_headers) {
                $value = mb_strlen($value) ? $value : $k;

                $this->invoke(
                  'import',
                  'pre cell',
                  $value,
                  $cell,
                  $options,
                  $k,
                  $j
                );

                $headers[$i][] = $value;
              }
              elseif ($keyed_by_headers) {
                $this->invoke(
                  'import',
                  'pre cell',
                  $value,
                  $cell,
                  $options,
                  $k,
                  $j
                              );

                $data[$i][$j - 1][$headers[$i][$k]] = $value;

                $this->invoke(
                                'import',
                                'post cell',
                                $data[$i][$j - 1][$headers[$i][$k]],
                                $cell,
                                $options,
                                $k,
                                $j
                              );
              }
              else {
                $col_index = $k;
                if ($cells->getIterateOnlyExistingCells()) {
                  $col_index = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                }

                $this->invoke(
                  'import',
                  'pre cell',
                  $value,
                  $cell,
                  $options,
                  $col_index,
                  $j
                              );

                $data[$i][$j][$col_index] = $value;

                $this->invoke(
                                'import',
                                'post cell',
                                $data[$i][$j][$col_index],
                                $cell,
                                $options,
                                $col_index,
                                $j
                              );
              }

              $k++;
            }

            $j++;
          }

          if (!$keyed_by_worksheet) {
            $i++;
          }
        }

        // Free up memory.
        $xls_data->disconnectWorksheets();
        unset($xls_data);

        return $data;
      }
      else {
        $this->logger->error("Couldn't find the PhpSpreadsheet library. Excel import
aborted.");

        return self::PHPEXCEL_ERROR_LIBRARY_NOT_FOUND;
      }
    }
    else {
      $this->logger->error(
        "The path '@path' is not readable. Excel import aborted.",
        ['@path' => $path]
          );

      return self::PHPEXCEL_ERROR_FILE_NOT_READABLE;
    }
  }

  /**
   * Invokes phpexcel hooks.
   *
   * We need a custom hook-invoke method, because we need to pass parameters by
   * reference.
   */
  public function invoke($hook, $op, &$data, $phpexcel, $options, $column = NULL, $row = NULL) {
    foreach ($this->moduleHandler->getImplementations('phpexcel_' . $hook) as $module) {
      $function = $module . '_phpexcel_' . $hook;
      $function($op, $data, $phpexcel, $options, $column, $row);
    }
  }

  /**
   * Munges the filename in the path.
   *
   * We can't use drupals file_munge_filename() directly
   * because the $path variable contains the path as well.
   *
   * @param string $path
   *   Separate the filename from the directory structure, munge it and return.
   *
   * @return string
   *   The filename.
   */
  public function mungeFilename($path) : string {
    $parts = explode(DIRECTORY_SEPARATOR, $path);

    $filename = array_pop($parts);

    $event = new FileUploadSanitizeNameEvent($filename, 'xls xlsx csv ods');
    $sanitized_filename = $event->getFilename();
    $this->eventDispatcher->dispatch($event);
    return implode(DIRECTORY_SEPARATOR, $parts) . DIRECTORY_SEPARATOR . $sanitized_filename;
  }

  /**
   * Determine the cache settings.
   *
   * Based on the site configuration, return the correct cache settings
   * and method to be used by PHPExcel.
   *
   * @return array
   *   The first key is the caching method, the second the settings.
   */
  public function getCacheSettings() {
    $cache_method = [];
    switch ($this->phpexcelConfig->get('cache_mechanism')) {
      case 'cache_in_memory_serialized':
        // $cache_method = CellsFactory::cache_in_memory_serialized;
        break;

      case 'cache_in_memory_gzip':
        // $cache_method = CellsFactory::cache_in_memory_gzip;
        break;

      case 'cache_to_phpTemp':
        // $cache_method = CellsFactory::cache_to_phpTemp;
        $this->cacheSettings = [
          'memoryCacheSize' => $this->phpexcelConfig->get('phptemp_limit') . 'MB',
        ];
        break;

      case 'cache_to_apc':
        // $cache_method = CellsFactory::cache_to_apc;
        $this->cacheSettings = [
          'cacheTime' => $this->phpexcelConfig->get('apc_cachetime'),
        ];
        break;

      case 'cache_to_memcache':
        // $cache_method = CellsFactory::cache_to_memcache;
        $this->cacheSettings = [
          'memcacheServer' => $this->phpexcelConfig->get('memcache_host'),
          'memcachePort' => $this->phpexcelConfig->get('_memcache_port'),
          'cacheTime' => $this->phpexcelConfig->get('memcache_cachetime'),
        ];
        break;

      case 'cache_to_sqlite3':
        // $cache_method = CellsFactory::cache_to_sqlite3;
        break;

      default:
        // $cache_method = CellsFactory::cache_in_memory;
        break;
    }

    return $cache_method;
  }

}
