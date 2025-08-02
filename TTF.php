<?php

class TTF
{
    public const offsetTableFormat = [
        'sfntVersion' => 'C4',//0x00010000   OTTO
        'numTables' => 'S',
        'searchRange' => 'S',//(max2^n <= numTables) * 16
        'entrySelector' => 'S',//log2(max2^n)
        'rangeShift' => 'S'//numTables * 16 - searchRange
    ];
    public const tableDirectoryFormat = [
        'tag' => 'C4',
        'checkSum' => 'L',
        'offset' => 'L',
        'length' => 'L'
    ];

    public const tableFormat = [
        'head' => [
            'version' => 'C4',//0x00010000
            'fontRevision' => 'C4',
            'checkSumAdjustment' => 'L',
            'magicNumber' => 'L',//0x5F0F3CF5
            'flags' => 'S',
            'unitsPerEm' => 'S',//2048  1000
            'created' => 'Q',
            'modified' => 'Q',
            'xMin' => 'c2',
            'yMin' => 'c2',
            'xMax' => 'c2',
            'yMax' => 'c2',
            'macStyle' => 'S',
            'lowestRecPPEM' => 'S',
            'fontDirectionHint' => 's',//0-2
            'indexToLocFormat' => 's',//0=短, 1=长
            'glyphDataFormat' => 's'//0
        ],
        'maxp' => [
            'version' => 'C4',//0x00005000
            'numGlyphs' => 'S',
            'maxPoints' => 'S',
            'maxContours' => 'S',
            'maxCompositePoints' => 'S',
            'maxCompositeContours' => 'S',
            'maxZones' => 'S',//通常为2
            'maxTwilightPoints' => 'S',
            'maxStorage' => 'S',
            'maxFunctionDefs' => 'S',
            'maxInstructionDefs' => 'S',
            'maxStackElements' => 'S',
            'maxSizeOfInstructions' => 'S',
            'maxComponentElements' => 'S',
            'maxComponentDepth' => 'S',
        ],
        'hhea' => [
            'version' => 'C4',//0x00010000
            'ascent' => 'c2',
            'descent' => 'c2',
            'lineGap' => 'c2',
            'advanceWidthMax' => 'C2',
            'minLeftSideBearing' => 'c2',
            'minRightSideBearing' => 'c2',
            'xMaxExtent' => 'c2',
            'caretSlopeRise' => 's',
            'caretSlopeRun' => 's',
            'caretOffset' => 's',
            'reserved0' => 's', //0
            'reserved1' => 's', //0
            'reserved2' => 's', //0
            'reserved3' => 's', //0
            'metricDataFormat' => 's', //0
            'numberOfHMetrics' => 'S'//hmtx表条目数
        ],
        'cmap' => [
            'version' => 'S',
            'numTables' => 'S',
            'Encoding' => [
                'platformID' => '',
                'encodingID' => '',
                'offset' => '',
            ],
            'SubtableFormat4' => [
                'endCode',
                'startCode',
                'idDelta',
                'idRangeOffset'
            ],
            'SubtableFormat12' => [
                'groups',
                'startCharCode',
                'endCharCode',
                'startGlyphID'
            ],
        ],
        'loca' => [
            'itemShort' => 'S',
            'itemLong' => 'L',
            'numGlyphs'
        ],
        'glyf' => [
            'simple' => [
                'header',
                'contourEndPts',
                'instructionLen',
                'instructions',
                'flags',
                'XCoordinates',
                'YCoordinates'
            ],
            'complex' => [
                'header',
                'component' => [
                    'flags',
                    'glyphIndex',
                    'arg1',
                    'arg2',
                    'transform'
                ],
                'instrunctions'
            ]
        ],
        'hmtx' => [
            'advanceWidth' => 'S',
            'lsb' => 'S',
            'lsb' => 'S',
            'advanceWidth'
        ],
        'name' => [],
        'os/2' => [],
        'post' => [],
    ];

    public $offset = [];
    public $directory = [];
    public $fontData = '';
    public $tables = [];

    public static function getFormat($format)
    {
        $f = [];
        foreach ($format as $n => $f) {
            $f[] = "$f$n";
        }
        return join('/', $f);
    }
    public function getOffsetTable()
    {
        $this->offset = unpack(self::getFormat(self::offsetTableFormat), $this->fontData);
    }

    public function getTableDirectory()
    {
        $offset = 12;
        $format = self::getFormat(self::tableDirectoryFormat);
        for ($i = 0; $i < $this->offset['numTables']; $i++) {
            $this->directory[] = unpack($format, $this->fontData, $offset);
            $offset += 16;
        }
    }

    public function getTables()
    {
        foreach ($this->directory as $table) {
            $format = self::getFormat(self::tableFormat[$table['tag']]);
            $this->tables[$table['tag']] = unpack($format, $this->fontData, $table['offset']);
        }
    }
}
