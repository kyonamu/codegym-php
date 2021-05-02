<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
    // ログインしている
    $_SESSION['time'] = time();

    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute(array($_SESSION['id']));
    $member = $members->fetch();
} else {
    // ログインしていない
    header('Location: login.php');
    exit();
}

// rt元投稿を挿入と削除
if (isset($_REQUEST['rt'])) {
    $id = $_REQUEST['rt'];

    $rts = $db->prepare('SELECT count(retweet_post_id) as cnt FROM posts WHERE retweet_post_id=?');
    $rts->execute(array($id));
    $rt = $rts->fetch();
    if ($rt['cnt'] == 0) {
        $retweet = $db->prepare('INSERT INTO posts SET member_id=?,retweet_post_id=?,created=NOW()');
        $retweet->execute(array($_SESSION['id'], $id));
    } else {
        $retweet_del = $db->prepare('DELETE FROM posts WHERE retweet_post_id=?');
        $retweet_del->execute(array($id));
    }
    header('Location:index.php');
    exit();
}

// rt先の投稿削除
if (isset($_REQUEST['rt_on'])) {
    $id = $_REQUEST['rt_on'];

    $rts_before = $db->prepare('SELECT * FROM posts WHERE id=?');
    $rts_before->execute(array($id));
    $rt_before = $rts_before->fetch();
    if (isset($rt_before)) {
        $retweet_de = $db->prepare('DELETE FROM posts WHERE id=?');
        $retweet_de->execute(array($id));
    }
    header('Location:index.php');
    exit();
}

// 投稿を記録する
if (!empty($_POST)) {
    if ($_POST['message'] != '') {
        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?,created=NOW()');
        $message->execute(array(
            $member['id'],
            $_POST['message'],
            $_POST['reply_post_id'],
        ));
        header('Location: index.php');
        exit();
    }
}

// 投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
    $page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// 返信の場合
if (isset($_REQUEST['res'])) {
    $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response->execute(array($_REQUEST['res']));

    $table = $response->fetch();
    $message = '@' . $table['name'] . ' ' . $table['message'];
}

// htmlspecialcharsのショートカット
function h($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value)
{
    return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ひとこと掲示板</title>

    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <div id="wrap">
        <div id="head">
            <h1>ひとこと掲示板</h1>
        </div>
        <div id="content">
            <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
            <form action="" method="post">
                <dl>
                    <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
                    <dd>
                        <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
                        <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
                    </dd>
                </dl>
                <div>
                    <p>
                        <input type="submit" value="投稿する" />
                    </p>
                </div>
            </form>
            <?php foreach ($posts as $post) : ?>
                <?php
                $retweet = $db->prepare('SELECT * FROM posts WHERE member_id=? AND retweet_post_id=?');
                $retweet->execute(array(
                    $_SESSION['id'],
                    $post['id']
                ));
                $retweet_record = $retweet->fetch();
                ?>
                <?php
                $rt_on = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=?');
                $rt_on->execute(array($post['retweet_post_id']));
                $rt_post = $rt_on->fetch();
                ?>
                <div class="msg">
                    <?php if ($post['retweet_post_id'] == 0) : ?>
                        <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
                        <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>
                        <?php else : ?>
                            <!-- rt時の名前、画像 -->
                            <?php echo $post['name'] . 'さんがリツイートしました。' . '<br>';    ?>
                            <img src="member_picture/<?php echo h($rt_post['picture']); ?>" width="48" height="48" alt="<?php echo h($rt_post['name']); ?>" />
                            <p><?php echo makeLink(h($rt_post['message'])); ?><span class="name">（<?php echo h($rt_post['name']); ?>）</span>
                            <?php endif; ?>
                            [<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>

                            <p class="day">
                                <!-- 課題：リツイートといいね機能の実装 -->
                                <span class="retweet">
                                    <!-- rt元 -->
                                    <?php if ((int)$post['retweet_post_id'] == 0) : ?>
                                        <?php if ((int)$post['id'] == (int)$retweet_record['retweet_post_id']) : ?>
                                            <a href="index.php?rt=<?php echo h($post['id']); ?>">
                                                <img class="retweet-image" src="images/retweet-solid-blue.svg"><span style="color:gray;"></span>
                                            </a>
                                        <?php else : ?>
                                            <a href="index.php?rt=<?php echo h($post['id']); ?>">
                                                <img class="retweet-image" src="images/retweet-solid-gray.svg"><span style="color:gray;"></span>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <!-- rt先 -->
                                    <?php if ((int)$post['retweet_post_id'] > 0) : ?>
                                        <?php if ((int)$post['retweet_post_id'] > 0) : ?>
                                            <a href="index.php?rt_on=<?php echo h($post['id']); ?>">
                                                <img class="retweet-image" src="images/retweet-solid-blue.svg"><span style="color:gray;"></span>
                                            </a>
                                        <?php else : ?>
                                            <a href="index.php?rt=<?php echo h($post['id']); ?>">
                                                <img class="retweet-image" src="images/retweet-solid-gray.svg"><span style="color:gray;"></span>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <!-- rt元のrt数 -->
                                    <?php
                                    if ((int)$post['retweet_post_id'] == 0) {
                                        $count_rt_posts = $db->prepare('SELECT COUNT(retweet_post_id) as cnt FROM posts WHERE retweet_post_id=?');
                                        $count_rt_posts->execute(array($post['id']));
                                        $ful_post = $count_rt_posts->fetch();
                                        echo $ful_post['cnt'];
                                    }
                                    ?>
                                    <!-- rt先のrt数 -->
                                    <?php
                                    if ((int)$post['retweet_post_id'] > 0) {
                                        $count_rt_posts = $db->prepare('SELECT COUNT(retweet_post_id) as cnt FROM posts WHERE retweet_post_id=?');
                                        $count_rt_posts->execute(array($post['retweet_post_id']));
                                        $ful_post = $count_rt_posts->fetch();
                                        echo $ful_post['cnt'];
                                    }
                                    ?>
                                </span>
                                <!-- いいね機能 -->
                                <?php
                                $favorites = $db->prepare('SELECT COUNT(*) AS cnt FROM favorites WHERE post_id=?');
                                $favorites->execute(array($post['id']));
                                $fav = $favorites->fetch();
                                ?>
                                <!-- rt時のいいね機能 -->
                                <?php
                                $rt_favorites = $db->prepare('SELECT COUNT(*) AS cnt FROM favorites WHERE member_id=? AND post_id=?');
                                $rt_favorites->execute(array(
                                    $_SESSION['id'],
                                    $post['retweet_post_id']
                                ));
                                $rt_fav = $rt_favorites->fetch();
                                ?>
                                <span class="favorite">
                                    <!-- rt元 -->
                                    <?php if ((int)$post['retweet_post_id'] == 0) : ?>
                                        <?php if ($fav['cnt'] != 0) : ?>
                                            <a href="favorite.php?id=<?php echo h($post['id']); ?>"><img class="favorite-image" src="images/heart-solid-red.svg"><span style="color:gray;"></span></a>
                                        <?php else : ?>
                                            <a href="favorite.php?id=<?php echo h($post['id']); ?>"><img class="favorite-image" src="images/heart-solid-gray.svg"><span style="color:gray;"></span></a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <!-- rt先 -->
                                    <?php if ((int)$post['retweet_post_id'] > 0) : ?>
                                        <?php if ($rt_fav['cnt'] != 0) : ?>
                                            <a href="favorite.php?id=<?php echo h($post['id']); ?>"><img class="favorite-image" src="images/heart-solid-red.svg"><span style="color:gray;"></span></a>
                                        <?php else : ?>
                                            <a href="favorite.php?id=<?php echo h($post['id']); ?>"><img class="favorite-image" src="images/heart-solid-gray.svg"><span style="color:gray;"></span></a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <!-- rtされてない時のfavo数 -->
                                    <?php
                                    if ((int)$post['retweet_post_id'] == 0) {
                                        $cnt = $db->prepare('SELECT COUNT(*) AS cnt FROM favorites WHERE post_id=?');
                                        $cnt->execute(array($post['id']));
                                        $fav_cnt = $cnt->fetch();
                                        if ($fav_cnt['cnt'] > 0) {
                                            echo $fav_cnt['cnt'];
                                        }
                                    }
                                    ?>
                                    <!-- rt時のfav数 -->
                                    <?php
                                    if ((int)$post['retweet_post_id'] > 0) {
                                        $cnt = $db->prepare('SELECT COUNT(*) AS cnt FROM favorites WHERE post_id=?');
                                        $cnt->execute(array($post['retweet_post_id']));
                                        $fav_cnt = $cnt->fetch();
                                        if ($fav_cnt['cnt'] > 0) {
                                            echo $fav_cnt['cnt'];
                                        }
                                    }
                                    ?>
                                </span>


                                </form>


                                <a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
                                <?php
                                if ($post['reply_post_id'] > 0) :
                                ?><a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">
                                        返信元のメッセージ</a>
                                <?php
                                endif;
                                ?>
                                <?php
                                if ($_SESSION['id'] == $post['member_id']) :
                                ?>
                                    [<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color: #F33;">削除</a>]
                                <?php
                                endif;
                                ?>
                            </p>
                </div>
            <?php
            endforeach;
            ?>

            <ul class="paging">
                <?php
                if ($page > 1) {
                ?>
                    <li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
                <?php
                } else {
                ?>
                    <li>前のページへ</li>
                <?php
                }
                ?>
                <?php
                if ($page < $maxPage) {
                ?>
                    <li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
                <?php
                } else {
                ?>
                    <li>次のページへ</li>
                <?php
                }
                ?>
            </ul>
        </div>
    </div>
</body>

</html>
