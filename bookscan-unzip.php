<?php

if (!isset($argv[1])) {
    echo 'Usage: `php bookscan-unzip.php /path/to/zips_dir`', PHP_EOL;
    exit(1);
}

define('TARGET', rtrim($argv[1], '/'));
define('DATA_DIR', TARGET . '/data');

$targets = array();
$missings = array();

chdir(TARGET);

$zips = glob('*.zip');
foreach ($zips as $name) {
    if (preg_match('/^(.+?)(?: *(?:（|\()?(\d+)(?:）|\))?)?( +（[^）]+）)? +([^）]+) +((?:\d+)?p(?:_\d+X?)?) *\.zip$/u', $name, $match)) {
        $data = array(
            'zip' => $match[0],
            'title' => $match[1],
            'num' => strlen($match[2]) ? sprintf('%02d', $match[2]) : null,
            'author' => trim($match[4]),
        );

        $series = sprintf('[%s] %s', $data['author'], $data['title']);
        if (!isset($targets[$series])) {
            $targets[$series] = array();
        }

        $data['file'] = $series;
        if ($data['num'] !== null) {
            $data['file'] .= ' (' . $data['num'] . ')';
        }

        $targets[$series][] = $data;
    } else {
        $missings[] = $name;
    }
}

if (count($missings) > 0) {
    var_dump($missings);
    exit;
}

$count = count($zips);
echo $count, ' zips found', PHP_EOL;

$workingzip = DATA_DIR . '/working.zip';
$i = 0;
foreach ($targets as $series => $books) {
    if ($books[0]['num'] === null) {
        $dir = DATA_DIR;
    } else {
        $dir = DATA_DIR . '/' . $series;
    }

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    foreach ($books as $data) {
        $progress = sprintf('(%d/%d) ... ', ++$i, $count);
        $extractDir = $dir.'/'.$data['file'];

        if (is_dir($extractDir)) {
            echo $progress, $data['file'], ' already exists', PHP_EOL;
            continue;
        }

        if (false === copy($data['zip'], $workingzip)) {
            throw new \RuntimeException('copy zip failed');
        }

        $zip = new \ZipArchive;
        $success = $zip->open($workingzip);
        if ($success !== true) {
            throw new \InvalidArgumentException($data['zip'] . ' cannot open');
        }

        $a = explode('/', $zip->getNameIndex(0));
        $zipname = $a[0];

        $zip->extractTo($dir);
        $zip->close();

        rename($dir.'/'.$zipname, $extractDir);

        chdir($extractDir);
        foreach (glob('*') as $imagename) {
            rename($imagename, str_replace($zipname, '', $imagename));
        }
        chdir(TARGET);

        unlink($workingzip);

        echo $progress, $data['file'], ' has extracted', PHP_EOL;
    }
}

exit(0);
