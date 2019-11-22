<?php

$dataFile = __DIR__.'/dataset_44327_15.txt';
list($memoryMap, $requests, $pagingPhysAddress) = readInput($dataFile);

writeOutput(
    "$dataFile.result.txt",
    array_map(new Translator($memoryMap, $pagingPhysAddress), $requests)
);

function readInput($filename)
{
    $h = fopen($filename, 'r');
    if (!$h) {
        throw new Exception("Can not read file $filename");
    }

    list($memoryMapRowCount, $requestCount, $pagingPhysAddress) = readRow($h);

    $memoryMap = [];
    while ($memoryMapRowCount > 0) {
        list($physAddress, $value) = readRow($h);
        $memoryMap[$physAddress] = $value;
        $memoryMapRowCount -= 1;
    }

    $requests = [];
    while ($requestCount > 0) {
        $requests[] = readRow($h)[0];
        $requestCount -= 1;
    }

    return [$memoryMap, $requests, $pagingPhysAddress];
}

function readRow($h)
{
    return array_values(
        array_filter(
            array_map('trim', explode(' ', fgets($h))),
            function ($word) { return $word === '0' || $word; }
        )
    );
}

class Translator
{
    protected $memoryMap = [];
    protected $pagingPhysAddress;

    public function __construct($memoryMap, $pagingPhysAddress)
    {
        $this->memoryMap = $memoryMap;
        $this->pagingPhysAddress = $pagingPhysAddress;
    }

    public function __invoke($logicalAddress)
    {
        return $this->translate($logicalAddress);
    }

    public function translate($logicalAddress)
    {
        $address = $this->pagingPhysAddress;
        list($indexes, $offset) = $this->getIndexesAndOffsetFromLogicalAddress($logicalAddress);
        foreach ($indexes as $index) {
            $record = $this->getRecordFromTable($address, $index);
            $address = $this->getPhysAddressFromRecord($record);
            if (is_null($address)) {
                return 'fault';
            }
        }
        return gmp_strval(gmp_add($address, $offset));
    }

    public function getIndexesAndOffsetFromLogicalAddress($logicalAddress)
    {
        $indexes = [];
        for ($i = 39; $i >= 12; $i -= 9) {
            $indexes[] = cutBites($logicalAddress, $i, 9);
        }
        return [$indexes, cutBites($logicalAddress, 0, 12)];
    }

    public function getRecordFromTable($tablePhysAddress, $recordIndex)
    {
        $physAddress = gmp_strval(gmp_add($tablePhysAddress, $recordIndex * 8));
        return $this->dereferencePhysAddress($physAddress);
    }

    public function getPhysAddressFromRecord($record)
    {
        // the lowest bit is P: when P is set record in usage
        return (cutBites($record, 0, 1) === '0')
            ? null : cutBites($record, 12, 40) * pow(2, 12);
    }

    public function dereferencePhysAddress($physAddress)
    {
        return isset($this->memoryMap[$physAddress])
            ? $this->memoryMap[$physAddress] : 0;
    }
}

/**
 * From the lowest bit to highest
 *
 * @param int|string $num
 * @param int $start
 * @param int $offset
 * @return string
 */
function cutBites($num, $start, $offset)
{
    return gmp_strval('0b'.strrev(substr(strrev(prependZeros(gmp_strval($num, 2))), $start, $offset)));
}

function prependZeros($bitString)
{
    return str_pad($bitString, 64, '0', STR_PAD_LEFT);
}

function writeOutput($filename, $rows)
{
    if (!file_exists($filename)) {
        touch($filename);
    }
    $h = fopen($filename, 'w');
    if (!$h) {
        throw new Exception("Can not write file $filename");
    }
    foreach ($rows as $row) {
        fputs($h, $row.PHP_EOL);
    }
    fclose($h);
}
