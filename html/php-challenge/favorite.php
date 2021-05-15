<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id'])) {
  $id = $_REQUEST['id'];

  $favs = $db->prepare('SELECT COUNT(*) as cnt FROM favorites WHERE member_id=? AND post_id=?');
  $favs->execute(array($_SESSION['id'], $id));
  $fav = $favs->fetch();

  if ((int)$fav['cnt'] === 0) {
    $favorites_in = $db->prepare('INSERT INTO favorites SET member_id=?, post_id=?, created=NOW()');
    $favorites_in->execute(array($_SESSION['id'], $id));
  } else {
    $favorites_del = $db->prepare('DELETE FROM favorites WHERE member_id=? AND post_id=?');
    $favorites_del->execute(array(
      $_SESSION['id'],
      $id
    ));
  }
}

header('Location: index.php');
exit();
