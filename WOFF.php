<?php

class WOFF {
    public const HeaderFormat = [
        'signature' => 'N',//0x774F4646  WOFF
        'flavor' => 'N',
        'length' => 'N',
        'numTables' => 'n',
        'reserved' => 'n', //0
        'totalSfntSize' => 'N',
        'majorVersion' => 'n',
        'minorVersion' => 'n',
        'metaOffset' => 'N',
        'metaLength' => 'N',
        'metaOrigLength' => 'N',
        'privOffset' => 'N',
        'privLength' => 'N',
    ];
    public const TableDirectoryFormat = [
        'tag' => 'N',
        'offset' => 'N',
        'compLength' => 'N',
        'origLength' => 'N',
        'origChecksum' => 'N'
    ];
}