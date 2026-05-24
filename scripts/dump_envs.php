<?php
function readEnvFile($path){
    $res=[];
    if(!is_file($path)) return $res;
    $lines=file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($lines as $line){
        $trim=trim($line);
        if($trim===''||strpos($trim,'#')===0||strpos($trim,'=')===false) continue;
        list($k,$v)=explode('=',$trim,2);
        $res[trim($k)]=trim($v);
    }
    return $res;
}

$files=['.env.hosting.local','.env','.env.hosting'];
foreach($files as $f){
    $p=__DIR__.'/..'.DIRECTORY_SEPARATOR.$f;
    echo "--- $f (".(file_exists($p)?'exists':'missing').")\n";
    $vals=readEnvFile($p);
    foreach($vals as $k=>$v){
        echo "$k=$v\n";
    }
}

echo "ENV getenv DB_HOST=".var_export(getenv('DB_HOST'),true)."\n";
