<?php

// APKs are ZIP archives. Reject a truncated upload before offering it to phones.
function android_package_complete(string $path): bool
{
    $file = @fopen($path, 'rb');
    if ($file === false) { return false; }
    try {
        $size = fstat($file)['size'];
        if ($size < 22 || fread($file, 4) !== "PK\x03\x04") { return false; }
        $tailSize = min($size, 65557);
        if (fseek($file, $size - $tailSize) !== 0) { return false; }
        $tail = fread($file, $tailSize);
        for ($position = strlen($tail) - 22; $position >= 0; $position--) {
            if (substr($tail, $position, 4) !== "PK\x05\x06") { continue; }
            $end = unpack('vdisk/vdirectoryDisk/ventriesDisk/ventries/VdirectorySize/VdirectoryOffset/vcommentSize', substr($tail, $position + 4, 18));
            if ($position + 22 + $end['commentSize'] !== strlen($tail)) { continue; }
            $endOffset = $size - $tailSize + $position;
            if ($end['disk'] !== 0 || $end['directoryDisk'] !== 0 ||
                $end['entries'] === 0 || $end['entriesDisk'] !== $end['entries'] ||
                $end['directoryOffset'] + $end['directorySize'] !== $endOffset) { return false; }
            if (fseek($file, $end['directoryOffset']) !== 0) { return false; }
            return fread($file, 4) === "PK\x01\x02";
        }
        return false;
    } finally {
        fclose($file);
    }
}
