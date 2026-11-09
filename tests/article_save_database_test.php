<?php
// Only the isolated test MySQL; no website connection, credentials, mail, or uploaded user files.
if((int)getenv('TOS_RECORDS_TEST_PORT')!==3319)throw new RuntimeException('Use isolated MySQL on port 3319.');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('127.0.0.1','root','','',3319);$name='tos_article_'.bin2hex(random_bytes(6));
$db->query("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4");$db->select_db($name);
$db->query('CREATE TABLE blog_post(id INT AUTO_INCREMENT PRIMARY KEY,titolo VARCHAR(255),contenuto LONGTEXT,immagine VARCHAR(255),sezione VARCHAR(20),data_pubblicazione DATETIME) ENGINE=InnoDB');
$db->query('CREATE TABLE blog_media(id INT AUTO_INCREMENT PRIMARY KEY,post_id INT,tipo VARCHAR(20),file_path VARCHAR(255),ordine INT) ENGINE=InnoDB');
$source=file_get_contents(__DIR__.'/../api/gestione_blog.php');$source=substr($source,5,strpos($source,'$articoli = [];')-5);
$source=str_replace("require_once __DIR__ . '/../includi/admin_guard.php';",'$adminSection="calcio"; $adminIsEsport=false; function csrf_or_same_origin_require($key){}', $source);
$source=str_replace("require_once __DIR__ . '/../includi/db.php';",'', $source);
$source=str_replace('$mediaDir = __DIR__ . \'/../img/blog_media/\';','$mediaDir = sys_get_temp_dir()."/'.$name.'";', $source);
$source=str_replace('__DIR__',var_export(realpath(__DIR__.'/../api'),true),$source);
$fixture=__DIR__.'/../.mobile-tools/verification/'.$name.'.php';
function article_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$run=function(array $post,bool $partial)use($source,$fixture,$name):array{
    $setup='<?php $conn=new mysqli("127.0.0.1","root","",'.var_export($name,true).',3319);$conn->set_charset("utf8mb4"); $_SERVER["REQUEST_METHOD"]="POST";$_SERVER["HTTP_X_TOS_ARTICLE_FORM"]="1";$_POST='.var_export($post,true).';$_FILES='.var_export($partial?[($post['azione']==='modifica'?'media_mod':'media')=>['name'=>['photo.png'],'error'=>[UPLOAD_ERR_PARTIAL],'tmp_name'=>[''],'size'=>[20],'type'=>['image/png']]]:[],true).';';
    file_put_contents($fixture,$setup.$source);
    $process=proc_open([PHP_BINARY,'-d','extension=mbstring',$fixture],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
    article_check($status===0,'Sandboxed save failed: '.$error);return json_decode($output,true,512,JSON_THROW_ON_ERROR);
};
try {
    $post=['azione'=>'crea','titolo'=>'Test articolo','contenuto'=>'**Grassetto** _Corsivo_ ++Sotto++'];
    $failed=$run($post,true);article_check(!$failed['ok'] && str_contains($failed['error'],'solo in parte'),'Specific upload error');
    article_check((int)$db->query('SELECT COUNT(*) FROM blog_post')->fetch_row()[0]===0,'Failed upload does not publish a partial article');
    $saved=$run($post,false);article_check($saved['ok']===true,'Retry succeeds');$id=(int)$saved['article']['id'];
    $before=$db->query('SELECT * FROM blog_post WHERE id='.$id)->fetch_assoc();
    $failed=$run(['azione'=>'modifica','articolo_id'=>$id,'titolo_mod'=>'Edited','contenuto_mod'=>'Changed'],true);
    article_check(!$failed['ok'],'Failed edit reported');
    article_check($db->query('SELECT * FROM blog_post WHERE id='.$id)->fetch_assoc()===$before,'Failed edit restores saved article');
    echo "Article database: failed media rolls back create/edit, retry stores formatting, no partial publication.\n";
}finally{@unlink($fixture);$db->query("DROP DATABASE `{$name}`");}
