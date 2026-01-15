## The PHPExcel module allows developers to export/import data to/from real Excel files.

### Usage

Import data from Excel file.
```
$phpexcel = \Drupal::service('phpexcel');
$filepath = 'modules/contrib/phpexcel/tests/src/Functional/data/phpexcel.test.multi_sheet.xlsx';
$result = $phpexcel->import($filepath);
print_r($result);
```

Export data to as an Excel file.
```
$headers = ['Header 1', 'Header 2'];
$data = [
  ['Data 1.1', 'Data 1.2'],
  ['Data 2.1', 'Data 2.2'],
  ['Data 3.1', 'Data 3.2'],
];
$phpexcel = \Drupal::service('phpexcel');
$filepath = 'sites/default/files/test.xlsx';
$result = $phpexcel->export($headers, $data, $filepath);;
print_r($result);
```
