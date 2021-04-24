<?php
$array = explode(',', $_GET['array']);

$count = count($array);
for ($i = 0; $i < $count; $i++) {
  for ($a = 1; $a < $count; $a++) {
    $b = $a - 1;
    if ($array[$a] < $array[$b]) {
      $temp = $array[$b];
      $array[$b] = $array[$a];
      $array[$a] = $temp;
    }
  }
}

echo "<pre>";
print_r($array);
echo "</pre>";
