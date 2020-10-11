<?php
class ClipId {
    public $productId = 0;
    public $kdenliveId = 0;
    public function __construct($productId, $kdenliveId)
    {
        $this->productId = $productId;
        $this->kdenliveId = $kdenliveId;   
    }
}
class KdenLive
{
    public $fps = 25;
    public $prjId = 0;
    public $prodId = 0;
    public $playlistId = 0;
    public $ftId = 1;
    public $folderId = 1;
    public $tractorId = 1;
    private $projectRoot = '';
    private $name = '';
    public $height = 1080;
    public $width = 1920;
    public $folder = [];
    public $productPlayListName = 'main_bin';
    public $documentId = 0;
    public $productList = [];
    public $playlistProduct = [];
    public $playlistEntryList = [];
    public $playlist = [];
    public $playlistEntryListSecond = [];
    public $tractorList = [];
    protected $projectSecond = 0;
    private $tractorIdList = ['video' => [], 'audio' => []];

    public function __construct($prjName, $rootPath)
    {
        $this->name = $prjName;
        $this->projectRoot = $rootPath;
        $this->documentId();
    }

    public function getCurrentSecond() {
        return max($this->playlistEntryListSecond);
    }

    protected function documentId()
    {
        return $this->documentId = round(microtime(true) * 1000);
    }
    public function addTitleClip($res, $second = 5, $folderId = -1, $name = '')
    {
        return $this->addProdcut('kdenlivetitle', $res, $second, $folderId, $name);
    }
    public function addImgClip($res, $second = 5, $folderId = -1, $name = '')
    {
        return $this->addProdcut('qimage', $res, $second, $folderId, $name);
    }
    public function addColorClip($res, $second = 5, $folderId = -1, $name = '')
    {
        return $this->addProdcut('color', $res, $second, $folderId, $name);
    }

    public function addVideoClip($res, $second = 5, $folderId = -1, $name = '')
    {
        return $this->addProdcut('avformat-novalidate', $res, $second, $folderId, $name);
    }

    public function addAudioClip($res, $second = 5, $folderId = -1, $name = '')
    {
        return $this->addProdcut('avformat-novalidate', $res, $second, $folderId, $name);
    }

    protected function avformat($file, &$length, &$property)
    {
        $meta = getVideoMeta($file);
        $length = videoTime($meta['time']) * 25;
       
        $property .= '<property name="audio_index">1</property>
        <property name="video_index">0</property>
        <property name="mute_on_pause">0</property>
        <property name="meta.media.color_range">mpeg</property>
        <property name="meta.media.0.stream.type">video</property>
        <property name="meta.media.1.codec.channels">2</property>
        <property name="meta.media.nb_streams">2</property>
        <property name="meta.media.1.stream.type">audio</property>
        <property name="meta.media.frame_rate_den">1</property>
        <property name="meta.media.colorspace">601</property>
        <property name="meta.media.color_trc">2</property>';
        
        $map = ['seekable' => 'seekable', 'aspect_ratio' => 'video_aspect_ratio',
            'meta.media.0.stream.frame_rate' => 'fps',
            'meta.media.0.stream.sample_aspect_ratio'=>'video_aspect_ratio',
            'meta.media.0.codec.width' => 'width',
            'meta.media.0.codec.height' => 'height',
            'meta.media.0.codec.frame_rate' => 'fps',
            'meta.media.0.codec.pix_fmt'=> 'video_pix_fmt',
            'meta.media.0.codec.sample_aspect_ratio' => 'video_aspect_ratio',
            'meta.media.0.codec.name' => 'video_codename',
            'meta.media.0.codec.bit_rate' => 'video_bitrate',
            'meta.attr.0.stream.handler_name.markup' => 'video_handler_name',
            'meta.media.1.codec.sample_fmt' => 'audio_fmt',
            'meta.media.1.codec.sample_rate' => 'audio_rate',
            'meta.media.1.codec.name' => 'audio_codename',
            'meta.media.1.codec.bit_rate' => 'audio_rate',
            'meta.attr.1.stream.handler_name.markup' => 'audio_handler_name',
            'meta.attr.major_brand.markup' => 'mjor',
            'meta.attr.minor_version.markup' => 'minor',
            'meta.attr.compatible_brands.markup' => 'compatible_brands',
            'meta.attr.encoder.markup' => 'encoder',
            'meta.media.sample_aspect_num' => 'video_aspect_v',
            'meta.media.sample_aspect_den' => 'video_aspect_h',
            'meta.media.frame_rate_num' => 'fps',];
        foreach($map as $pk => $vk) {
            $property .="<property name=\"$pk\">{$meta[$vk]}</property>";
        }
        return [$meta['width'], $meta['height']];
    }

    protected function checkChipMediaSize($mltService, $res, &$len, &$property = '')
    {
        if ($mltService == 'qimage') {
            return getimagesize($res);
        } elseif ($mltService == 'avformat-novalidate') {
            return $this->avformat($res, $len, $property);
        } else {
            return [$this->width, $this->height];
        }
    }

    public function addProdcut($mltService, $res, $second = 5, $folderId = -1, $name = '')
    {
        $out = videoTime($second);
        $len = $second * $this->fps;
        $this->prodId++;
        $prodId = $this->prodId;
        $prjId = $prodId;
        $chipName = $name ? "<property name=\"kdenlive:clipname\">$name</property>" : '<property name="kdenlive:clipname"/>';
        $property = '';
       
        $filesize = filesize($res);
        list($width, $height) = $this->checkChipMediaSize($mltService, $res, $len, $property);

        if ($mltService == 'color') {
            $property .= '<property name="mlt_image_format">rgb24</property>';
        }
        $tpl = <<<EOF
                <producer id="producer{$prodId}" in="00:00:00.000" out="$out">
                <property name="length">$len</property>
                <property name="eof">pause</property>
                <property name="resource">$res</property>
                <property name="progressive">1</property>
                <property name="aspect_ratio">1</property>
                <property name="seekable">1</property>
                <property name="ttl">{$this->fps}</property>
                <property name="mlt_service">$mltService</property>
                <property name="kdenlive:duration">$len</property>
                $chipName $property
                <property name="kdenlive:folderid">$folderId</property>
                <property name="kdenlive:id">$prjId</property>
                <property name="force_reload">0</property>
                <property name="meta.media.width">$width</property>
                <property name="meta.media.height">$height</property>
                <property name="kdenlive:file_size">$filesize</property>
                <property name="kdenlive:duration">$out</property>
                </producer>\n
                EOF;
        $this->productList[$prodId] = $tpl;
        $this->addToFolder($prodId, $second);
        return new ClipId($prodId, $prjId);
    }

    protected function addProdcutToPlaylist($playlistid, $prodId)
    {
        $tpl = $this->productList[$prodId];
        $this->prodId++;
        $tpl = preg_replace('/"producer[0-9]+"/', "\"producer{$this->prodId}\"", $tpl);
        $this->playlistProduct[$playlistid][] = $tpl;
    }

    protected function addToFolder($prodId, $second)
    {
        $out = videoTime($second);
        $this->productToFolder[] = "<entry producer=\"producer$prodId\" in=\"00:00:00.000\" out=\"$out\"/>\n";
    }
    public function addToPlaylist($playlistId, ClipId $clipPid, $outSecond, $inSecond = 0, $filter = '')
    {
        $this->addProdcutToPlaylist($playlistId, $clipPid->productId);
        $out = videoTime($outSecond);
        $in = videoTime($inSecond);
        $isfilter = $filter ? '<property name="kdenlive:activeeffect">1</property>' : '';
        $tpl  = <<<EOF
                <entry producer="producer{$clipPid->productId}" in="$in" out="$out">
                <property name="kdenlive:id">{$clipPid->kdenliveId}</property>$isfilter{$filter}
                </entry>\n
                EOF;
        $this->playlistEntryList[$playlistId][] = $tpl;
        $this->playlistEntryListSecond[$playlistId] += ($outSecond - $inSecond);
    }

    public function addPlaylistBlankEntry($playlistId, $second)
    {
        $length = videoTime($second);
        $this->playlistEntryList[$playlistId][] = "<blank length=\"$length\"/>";
        $this->playlistEntryListSecond[$playlistId] += $second;
    }

    public function addPlaylist($tractorId, $isVideo = true)
    {
        $id = $this->playlistId;
        $this->playlist[$tractorId][$id] = $isVideo;
        $this->playlistEntryList[$id] = [];
        $this->playlistEntryListSecond[$id] = 0;
        $this->playlistId++;
        return $id;
    }

    protected function buildAllPlaylist()
    {
        foreach ($this->playlist as $tractorId => $config) {
            foreach ($config as $id => $isVideo) {
                if ($isVideo === null) {
                    continue;
                }
                $this->buildPlaylist($tractorId, $id, $isVideo);
            }
        }
    }

    protected function buildPlaylist($tractorId, $playid, $isVideo)
    {
        $entryList = $tpl = '';
        if (isset($this->playlistProduct[$playid])) {
            $tpl .= join($this->playlistProduct[$playid]);
        }
        if (isset($this->playlistEntryList[$playid])) {
            $entryList = join($this->playlistEntryList[$playid]);
        }

        $tpl .= <<<EOF
            <playlist id="playlist{$playid}">
            $entryList
            EOF;
        if (!$isVideo) {
            $tpl .= <<<EOF
                <property name="kdenlive:audio_track">1</property>
                <filter id="filter3">
                <property name="window">75</property>
                <property name="max_gain">20dB</property>
                <property name="mlt_service">volume</property>
                <property name="internal_added">237</property>
                <property name="disable">0</property>
                </filter>
                <filter id="filter4">
                <property name="channel">-1</property>
                <property name="mlt_service">panner</property>
                <property name="internal_added">237</property>
                <property name="start">0.5</property>
                <property name="disable">0</property>
                </filter>
                EOF;
        }
        $tpl .= '</playlist>';
        $this->playlist[$tractorId][$playid] = $tpl;
    }

    public function addNumPairTractor($num, $names = [])
    {
        $ids = ['audio' => [], 'video' => []];
        $noName = empty($names);
        $playlist = [];
        for ($i = 0; $i < $num; $i++) {
            $tractorId = $this->addTractor(false, $noName ? '' : $names[$i] . '_audio', $playlist);
            $ids['audio'][$tractorId] = $playlist;
        }
        for ($i = 0; $i < $num; $i++) {
            $tractorId = $this->addTractor(true, $noName ? '' : $names[$i] . '_video', $playlist);
            $ids['video'][$tractorId] = $playlist;
        }
        return $ids;
    }

    public function addTractor($isVideo = true, $name = '', &$playlist = [])
    {
        $playlist = [];
        $id = $this->tractorId;
        $this->tractorList[$id] = [$isVideo, $name];
        $type = $isVideo ? 'video' : 'audio';
        $playlist[$type] = $this->addPlaylist($id, false);
        $playlist['empty'] = $this->addEmptyPlaylist($id);
        $this->tractorId++;
        $this->tractorIdList[$type][$id] = $playlist;
        return $id;
    }

    public function getTractorInfo()
    {
        return $this->tractorIdList;
    }

    protected function buildAllTractor()
    {
        $this->buildAllPlaylist();

        foreach ($this->tractorList as $tractorId => $tractor) {
            $this->buildTractor($tractorId, $tractor[0], $tractor[1]);
        }
        $this->endTractor();
    }

    protected function buildTractor($tractorId, $isVideo = true, $name = '')
    {
        $tpl = $track = ''; $tractorSecond = 0;
        if (isset($this->playlist[$tractorId])) {
        
            foreach ($this->playlist[$tractorId] as $playid => $config) {
                if ($config === null) {
                    $tpl .= "<playlist id=\"playlist{$playid}\"/>";
                    $track .= "<track hide=\"both\" producer=\"playlist{$playid}\"/>";
                } else {
                    $tractorSecond += $this->playlistEntryListSecond[$playid];
                    $tpl .= $config;
                    $hide = $isVideo ? 'audio' : 'video';
                    $track .= "<track hide=\"$hide\" producer=\"playlist{$playid}\"/>";
                }
            }
        }
        $tractorTime = videoTime($tractorSecond);
        $isName = $name ? "<property name=\"kdenlive:track_name\">$name</property>" : '';
        $isAudio = $isVideo ? '' : '<property name="kdenlive:audio_track">1</property>';
        $tpl .= <<<EOF
                <tractor id="tractor{$tractorId}" in="00:00:00.000" out="$tractorTime">
                $isAudio
                <property name="kdenlive:trackheight">67</property>
                <property name="kdenlive:collapsed">0</property>
                <property name="kdenlive:thumbs_format"/>
                <property name="kdenlive:audio_rec"/>
                <property name="kdenlive:timeline_active">1</property>
                $isName
                $track
                <filter id="filter2">
                <property name="iec_scale">0</property>
                <property name="mlt_service">audiolevel</property>
                <property name="disable">1</property>
                </filter>
            </tractor>
            EOF;
        $this->tractorList[$tractorId] = $tpl;
    }
    protected function endTractor()
    {
        $tracks = '';
        foreach (array_keys($this->tractorList) as $tractorId) {
            $tracks .= "<track producer=\"tractor{$tractorId}\"/>";
        }
        $out = videoTime($this->projectSecond);
        $tpl = <<<EOF
        <tractor id="tractor{$this->tractorId}" global_feed="1" in="00:00:00.000" out="$out">
        <track producer="black_track"/>
        $tracks
        <transition id="transition0">
         <property name="a_track">0</property>
         <property name="b_track">1</property>
         <property name="mlt_service">mix</property>
         <property name="kdenlive_id">mix</property>
         <property name="internal_added">237</property>
         <property name="always_active">1</property>
         <property name="sum">1</property>
        </transition>
        <transition id="transition1">
         <property name="a_track">0</property>
         <property name="b_track">2</property>
         <property name="mlt_service">mix</property>
         <property name="kdenlive_id">mix</property>
         <property name="internal_added">237</property>
         <property name="always_active">1</property>
         <property name="sum">1</property>
        </transition>
        <transition id="transition2">
         <property name="a_track">0</property>
         <property name="b_track">3</property>
         <property name="compositing">0</property>
         <property name="distort">0</property>
         <property name="rotate_center">0</property>
         <property name="mlt_service">qtblend</property>
         <property name="kdenlive_id">qtblend</property>
         <property name="internal_added">237</property>
         <property name="always_active">1</property>
        </transition>
        <transition id="transition3">
         <property name="a_track">0</property>
         <property name="b_track">4</property>
         <property name="compositing">0</property>
         <property name="distort">0</property>
         <property name="rotate_center">0</property>
         <property name="mlt_service">qtblend</property>
         <property name="kdenlive_id">qtblend</property>
         <property name="internal_added">237</property>
         <property name="always_active">1</property>
        </transition>
        <filter id="filter6">
         <property name="window">75</property>
         <property name="max_gain">20dB</property>
         <property name="mlt_service">volume</property>
         <property name="internal_added">237</property>
         <property name="disable">1</property>
        </filter>
        <filter id="filter7">
         <property name="channel">-1</property>
         <property name="mlt_service">panner</property>
         <property name="internal_added">237</property>
         <property name="start">0.5</property>
         <property name="disable">1</property>
        </filter>
        <filter id="filter8">
         <property name="iec_scale">0</property>
         <property name="mlt_service">audiolevel</property>
         <property name="disable">1</property>
        </filter>
       </tractor>
       EOF;
        $this->tractorList[$this->tractorId] = $tpl;
    }

    protected function addBlackProducer()
    {
        $length = $this->projectSecond * $this->fps;
        $out = videoTime($this->projectSecond);
        return <<<EOF
                <producer id="black_track" in="00:00:00.000" out="$out">
                <property name="length">$length</property>
                <property name="eof">continue</property>
                <property name="resource">black</property>
                <property name="aspect_ratio">1</property>
                <property name="mlt_service">color</property>
                <property name="mlt_image_format">rgb24a</property>
                <property name="set.test_audio">0</property>
                </producer>
                EOF;
    }

    public function addEmptyPlaylist($tractorId)
    {
        $id = $this->playlistId;
        $this->playlist[$tractorId][$id] = null;
        $this->playlistId++;
        return $id;
    }

    public function createFilter($property, $outSecond, $inSecond = 0)
    {
        $out = videoTime($outSecond);
        $in = '';
        if ($inSecond) {
            $in = 'in="' . videoTime($inSecond) . '"';
        }
        $this->ftId++;
        $tpl = <<<EOF
            <filter id="filter{$this->ftId}" out="$out" $in>
            EOF;
        foreach ($property as $name => $val) {
            $tpl .= "<property name=\"$name\">$val</property>";
        }
        $tpl .= '</filter>';
        return $tpl;
    }

    public function ceateProject()
    {
        $project = <<<EOF
            <?xml version='1.0' encoding='utf-8'?>
            <mlt LC_NUMERIC="C" producer="{$this->productPlayListName}" version="6.22.1" root="{$this->projectRoot}">
            <profile frame_rate_num="{$this->fps}" sample_aspect_num="1" display_aspect_den="9" colorspace="709" progressive="1" description="HD {$this->height}p {$this->fps} fps" display_aspect_num="16" frame_rate_den="1" width="{$this->width}" height="{$this->height}" sample_aspect_den="1"/>
            EOF;
        $this->buildAllTractor();
        $project .= join($this->productList);
        $project .= $this->productPlaylist();
        $project .= $this->addBlackProducer();
        $project .= join($this->tractorList);
        $project .= '</mlt>';
        file_put_contents(pathJoin($this->projectRoot, $this->name . '.kdenlive'), $project);
    }

    public function addFolder($name)
    {
        $this->folderId++;
        $folder = <<<EOF
            <property name="kdenlive:folder.-1.{$this->folderId}">{$name}</property>
            EOF;
        $this->folder[$this->folderId] = $folder;
        return $this->folderId;
    }

    protected function productPlaylist()
    {
        $folder = join($this->folder);
        $folderIds = join(';', array_keys($this->folder));
        $products = join($this->productToFolder);
        $list = <<<EOF
            <playlist id="{$this->productPlayListName}">
            $folder
            <property name="kdenlive:docproperties.activeTrack">2</property>
            <property name="kdenlive:docproperties.audioChannels">2</property>
            <property name="kdenlive:docproperties.audioTarget">-1</property>
            <property name="kdenlive:docproperties.disablepreview">0</property>
            <property name="kdenlive:docproperties.documentid">$this->documentId</property>
            <property name="kdenlive:docproperties.enableTimelineZone">0</property>
            <property name="kdenlive:docproperties.enableexternalproxy">0</property>
            <property name="kdenlive:docproperties.enableproxy">0</property>
            <property name="kdenlive:docproperties.externalproxyparams"/>
            <property name="kdenlive:docproperties.generateimageproxy">0</property>
            <property name="kdenlive:docproperties.generateproxy">0</property>
            <property name="kdenlive:docproperties.kdenliveversion">20.08.0</property>
            <property name="kdenlive:docproperties.position">0</property>
            <property name="kdenlive:docproperties.previewextension"/>
            <property name="kdenlive:docproperties.previewparameters"/>
            <property name="kdenlive:docproperties.profile">atsc_{$this->height}p_{$this->fps}</property>
            <property name="kdenlive:docproperties.proxyextension"/>
            <property name="kdenlive:docproperties.proxyimageminsize">2000</property>
            <property name="kdenlive:docproperties.proxyimagesize">800</property>
            <property name="kdenlive:docproperties.proxyminsize">1000</property>
            <property name="kdenlive:docproperties.proxyparams"/>
            <property name="kdenlive:docproperties.scrollPos">0</property>
            <property name="kdenlive:docproperties.seekOffset">30000</property>
            <property name="kdenlive:docproperties.version">1</property>
            <property name="kdenlive:docproperties.verticalzoom">1</property>
            <property name="kdenlive:docproperties.videoTarget">2</property>
            <property name="kdenlive:docproperties.zonein">0</property>
            <property name="kdenlive:docproperties.zoneout">75</property>
            <property name="kdenlive:docproperties.zoom">8</property>
            <property name="kdenlive:expandedFolders">$folderIds</property>
            <property name="kdenlive:documentnotes"/>
            <property name="xml_retain">1</property>
            $products
            </playlist>
            EOF;
        return $list;
    }

    /**
     * Undocumented function
     *
     * @param string $img           处理的图片
     * @param float $nWidth         缩放到的宽度，等于0时，缩放倍数与高度同
     * @param float $nHeight        缩放到的高度，等于0时，缩放倍数与宽度同
     * @param integer $outWidth     计算后输出的宽度
     * @param integer $outHeight    计算后输出的高度
     * @param integer $oWidth       原图宽度
     * @param integer $oHeight      原图高度
     * @return string
     */
    public function imgToSize($img, $nWidth, $nHeight, &$outWidth = 0, &$outHeight = 0, &$oWidth = 0, &$oHeight = 0)
    {
        $size = getimagesize($img);
        list($oWidth, $oHeight) = $size;
        if (($nWidth < 0 || $nHeight < 0) || ($nWidth == 0 && $nHeight == 0)) {
            trigger_error('new img width/height can not be less than 0 or all equal to 0', E_USER_NOTICE);
            return false;
        } elseif ($nWidth > 0 && $nHeight === 0) {
            $outWidth = $nWidth;
            $hzoom = $wzoom = round($nWidth / $oWidth, 6);
            $outHeight = $oHeight * $hzoom;
        } elseif ($nHeight > 0 && $nWidth === 0) {
            $outHeight = $nHeight;
            $wzoom = $hzoom = round($nHeight / $oHeight, 6);
            $outWidth = $oWidth * $wzoom;
        } else if ($nWidth > 0 || $nHeight > 0) {
            $outHeight = $nHeight;
            $outWidth = $nWidth;
            $wzoom = round($nWidth / $oWidth, 6);
            $hzoom = round($nHeight / $oHeight, 6);
        }
        return "$wzoom,0,0,0,$hzoom,0,0,0,1";
    }

    public function createImgItem($img, $x, $y, $z, $transform)
    {
        $data = base64_encode(file_get_contents($img));
        return $this->createTitleItem('Pixmap', $x, $y, $z, $transform, ['base64' => $data]);
    }

    public function createTitleItem($type, $x, $y, $z, $transform, array $attr = [], $content = '')
    {
        $type = ucwords($type);
        $attrs = '';
        foreach ($attr as $key => $v) {
            $v = addcslashes($v, '"');
            $attrs .= "$key=\"$v\" ";
        }
        $content = $content ? ">$content</content>" : '/>';

        return <<<EOF
            <item type="QGraphics{$type}Item" z-index="$z">
            <position x="$x" y="$y">
            <transform>$transform</transform>
            </position>
            <content {$attrs}{$content}
            </item>
            EOF;
    }

    public function upMove($start = 0, $end = 0)
    {
        return $this->moveCamera($start, $end, 'y', 1);
    }

    public function downMove($start = 0, $end = 0)
    {
        return $this->moveCamera($start, $end, 'y', -1);
    }

    public function rightMove($start = 0, $end = 0)
    {
        return $this->moveCamera($start, $end, 'x', -1);
    }

    public function leftMove($start = 0, $end = 0)
    {
        return $this->moveCamera($start, $end, 'x', 1);
    }

    protected function moveCamera($start, $end, $coords, $ori)
    {
        $step = $coords == 'y' ? $this->height : $this->width;
        $startPos = $ori > 0 ? 0 : $step;
        $startOffset = $step * $start * $ori;
        $endOffset = $step * $end;
        $startPos = $startOffset;
        $endPos = $endOffset * $ori;
        return  $coords == 'y' ? [0, $startPos, 0, $endPos] : [$startPos, 0, $endPos, 0];
    }

    public function createTitle($second, $items = '', $bg = '255,255,255,0', $move = [0, 0, 0, 0])
    {
        $out = $second * $this->fps;
        return <<<EOF
            <kdenlivetitle duration="$out" LC_NUMERIC="C" width="{$this->width}" height="{$this->height}" out="$out">
            $items
            <startviewport rect="{$move[0]},{$move[1]},{$this->width},{$this->height}"/>
            <endviewport rect="{$move[2]},{$move[3]},{$this->width},{$this->height}"/>
            <background color="$bg"/>
            </kdenlivetitle>
            EOF;
    }

    public function textNotoSansWidth($text, $fontSize, &$line = 1, $nl = "\n")
    {
        $number = 0.6;
        $zh = 59 / 60;
        $dot = 21 / 60;
        $letter = [
            36 => ['a', 'g', 'v', 'y', 'F',],
            37 => ['Z', 'E'],
            38 => ['k', 'o', 'S', 'T', 'Y'],
            39 => ['b', 'd', 'h', 'n', 'p', 'q', 'u', 'V', 'X'],
            32 => 'c',
            35 => ['e', 'x', 'J', 'L', ''],
            25 => 'f',
            19 => ['i', 'j', 'l'],
            27 => 'r',
            30 => 's',
            59 => 'm',
            26 => 't',
            53 => 'w',
            31 => 'z',
            40 => ['A', 'C',],
            41 => ['B', 'P'],
            42 => 'R',
            43 => ['D', 'G'],
            46 => 'H',
            20 => 'I',
            44 => 'K',
            45 => ['N', 'U'],
            52 => 'M',
            56 => 'W',
            47 => ['O', 'Q'],
        ];
        $chars = mb_str_split($text);
        $size = 0;
        $resSize = [];
        foreach ($chars as $char) {
            if ($char == $nl) {
                $resSize[] = $size;
                $size = 0;
                continue;
            }
            if (strlen($char) > 1) {
                $size += $zh * $fontSize;
                continue;
            }
            $accii = ord($char);
            if (
                $accii < 48 ||
                ($accii > 57 && $accii < 65)
                || ($accii > 90 && $accii < 97)
                || ($accii > 122 && $accii < 127)
            ) {
                $size += $dot * $fontSize;
            } elseif ($accii > 47 && $accii < 58) {
                $size += $number * $fontSize;
            } else {
                foreach ($letter as $s => $chr) {
                    if ($chr == $char) {
                        $size += $fontSize * ($s / 60);
                    } else if (is_array($char) && ($subSize = array_search($char, $char))) {
                        $size += $fontSize * ($subSize / 60);
                    }
                }
            }
        }
        $resSize[] = $size;
        $line = count($resSize);
        return  $line > 0 ? max($resSize) : $size;
    }
}
