<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Path;

/**
 * Description of Video
 *
 * @author chopin
 */
class Video
{

    public static function ffmpegGetMediaMeta($file, $key, $flag)
    {
        $flags = [
            'AV_DICT_MATCH_CASE' => 1, 'AV_DICT_IGNORE_SUFFIX' => 2,
            'AV_DICT_DONT_STRDUP_KEY' => 4, 'AV_DICT_DONT_STRDUP_VAL' => 8,
            'AV_DICT_DONT_OVERWRITE' => 16, 'AV_DICT_APPEND' => 32, 'AV_DICT_MULTIKEY' => 64
        ];
        if(!isset($flags[$flag])) {
            trigger_error('av dict flags error', E_USER_NOTICE);
            return false;
        }

        $cCode = <<<EOF
    typedef struct AVDictionaryEntry {char *key;char *value;} AVDictionaryEntry;
    typedef struct AVDictionary {int count;AVDictionaryEntry *elems;} AVDictionary;
    typedef struct AVIOInterruptCB {int (*callback)(void*); void *opaque;} AVIOInterruptCB;
    typedef struct AVInputFormat {
    const char *name;const char *long_name;int flags;const char *extensions;
    const void *codec_tag;const void *priv_class;
    const char *mime_type;struct AVInputFormat *next;int raw_codec_id;int priv_data_size;
    int (*read_probe)(const void *);int (*read_header)(struct AVFormatContext *);
    int (*read_packet)(struct AVFormatContext *, void *pkt);int (*read_close)(struct AVFormatContext *);
    int (*read_seek)(struct AVFormatContext *,int stream_index, int64_t timestamp, int flags);
    int64_t (*read_timestamp)(struct AVFormatContext *s, int stream_index,int64_t *pos, int64_t pos_limit);
    int (*read_play)(struct AVFormatContext *);int (*read_pause)(struct AVFormatContext *);
    int (*read_seek2)(struct AVFormatContext *s, int stream_index, int64_t min_ts, int64_t ts, int64_t max_ts, int flags);
    int (*get_device_list)(struct AVFormatContext *s, struct AVDeviceInfoList *device_list);
    int (*create_device_capabilities)(struct AVFormatContext *s, struct AVDeviceCapabilitiesQuery *caps);
    int (*free_device_capabilities)(struct AVFormatContext *s, struct AVDeviceCapabilitiesQuery *caps);
    } AVInputFormat;
    typedef int (*av_format_control_message)(struct AVFormatContext *s, int type,
                                         void *data, size_t data_size);
    typedef struct AVFormatContext {
    const void *av_class;struct AVInputFormat *iformat;void *oformat;void *priv_data;
    void *pb;int ctx_flags;unsigned int nb_streams;void **streams;char *url;int64_t start_time;
    int64_t duration;int64_t bit_rate;unsigned int packet_size;int max_delay;int flags;int64_t probesize;
    int64_t max_analyze_duration;const uint8_t *key;int keylen;unsigned int nb_programs;
    void **programs;int video_codec_id;int audio_codec_id;int subtitle_codec_id;
    unsigned int max_index_size;unsigned int max_picture_buffer;unsigned int nb_chapters;
    void **chapters;AVDictionary *metadata;int64_t start_time_realtime;int fps_probe_size;
    int error_recognition;AVIOInterruptCB interrupt_callback;int debug;int64_t max_interleave_delta;
    int event_flags;int max_ts_probe;int avoid_negative_ts;int ts_id;int audio_preload;
    int max_chunk_duration;int max_chunk_size;int use_wallclock_as_timestamps;int avio_flags;
    int duration_estimation_method;int64_t skip_initial_bytes;unsigned int correct_ts_overflow;
    int seek2any;int flush_packets;int probe_score;int format_probesize;char *codec_whitelist;
    char *format_whitelist;void *internal;int io_repositioned;void *video_codec;void *audio_codec;
    void *subtitle_codec;void *data_codec;int metadata_header_padding;void *opaque;
    av_format_control_message control_message_cb;int64_t output_ts_offset;uint8_t *dump_separator;
    int data_codec_id;char *protocol_whitelist;
    int (*io_open)(struct AVFormatContext *s, void **pb, const char *url,int flags, AVDictionary **options);
    void (*io_close)(struct AVFormatContext *s, void *pb);char *protocol_blacklist;
    int max_streams;int skip_estimate_duration_from_pts; int max_probe_packets;
    } AVFormatContext;
    int avformat_open_input(AVFormatContext **ps, const char *url, AVInputFormat *fmt, AVDictionary **options);
    AVDictionaryEntry *av_dict_get(const AVDictionary *m, const char *key, const AVDictionaryEntry *prev, int flags);
    void avformat_close_input(AVFormatContext **ps);
    EOF;

        $ffi = FFI::cdef($cCode, '/usr/share/code/libffmpeg.so');
        $fmtCtx = FFI::addr($ffi->new('AVFormatContext', false, true));
        $tag = FFI::addr($ffi->new('AVDictionaryEntry'));

        $ffi->avformat_open_input(FFI::addr($fmtCtx), $file, NULL, NULL);
        $ret = [];
        if(empty($key)) {
            $key = '';
            while(($tag = $ffi->av_dict_get($fmtCtx->metadata, $key, $tag, $flags[$flag]))) {
                var_dump($tag);
                $key = FFI::string($tag->key);
                $tagValue = FFI::string($tag->value);
                $ret[$key] = $tagValue;
            }
        } else {
            $tag = $ffi->av_dict_get($fmtCtx->metadata, '', $tag, $flags[$flag]);
            $key = FFI::string($tag->key);
            $tagValue = FFI::string($tag->value);
            $ret[$key] = $tagValue;
        }
        $ffi->avformat_close_input(FFI::addr($fmtCtx));
        return $ret;
    }

    public static function getVideoMeta($file): array
    {
        $command = "ffmpeg -hide_banner -i '$file' 2>&1";
        $output = [];
        exec($command, $output, $returnVar);
        exec("mplayer -nolirc -vo null -ao null -frames 0 -identify '$file' 2>&1", $output, $returnVar);
        if($returnVar > 0) {
            trigger_error('use mplayer get video meta error');
            return [];
        }
        $meta = arrayFindFieldValue($output, [
            'major_brand',
            'minor_version',
            'compatible_brands',
            'encoder',
            'handler_name',
            'Duration',
            'Stream #0',
            'ID_VIDEO_BITRATE',
            'ID_AUDIO_BITRATE',
            'ID_SEEKABLE',
            'ID_VIDEO_FPS',
            'ID_VIDEO_WIDTH',
            'ID_VIDEO_HEIGHT'
        ]);
        $videoInfoArr = explode(',', $meta['Stream #0'][0]);
        $sarWH = [0, 0];
        $sarValue = 0;
        foreach($videoInfoArr as $vinfo) {
            if(($idx = strpos($vinfo, 'Video:')) !== false) {
                list($codename) = explode(' ', trim(substr($vinfo, $idx)), 2);
            } elseif(strpos($vinfo, '[SAR') !== false) {
                $size = explode(' ', trim($vinfo));
                $sarWH = explode(':', $size[2]);
                $sarValue = round($sarWH[0] / $sarWH[1], 6);
            }
        }
        $pixfmt = trim($videoInfoArr[1]);
        $audioInfo = explode(',', $meta['Stream #0'][1]);
        foreach($audioInfo as $ainfo) {
            if(($idx = strpos($ainfo, 'Audio:')) !== false) {
                list($acodename) = explode(' ', trim(substr($ainfo, $idx)), 2);
            } elseif(strpos($ainfo, 'Hz') !== false) {
                list($hz) = explode(' ', trim($ainfo), 2);
            }
        }
        $return = [];
        $durationInfo = explode(',', $meta['Duration']);
        $return['time'] = trim($durationInfo[0]);
        $return['width'] = $meta['ID_VIDEO_WIDTH'];
        $return['height'] = $meta['ID_VIDEO_HEIGHT'];
        $return['fps'] = $meta['ID_VIDEO_FPS'];
        $return['video_bitrate'] = $meta['ID_VIDEO_BITRATE'][0];
        $return['mjor'] = $meta['major_brand'][0];
        $return['minor'] = $meta['minor_version'][0];
        $return['compatible_brands'] = $meta['compatible_brands'][0];
        $return['encoder'] = $meta['encoder'][0];
        $return['seekable'] = $meta['ID_SEEKABLE'];
        $return['video_codename'] = $codename;
        $return['video_handler_name'] = $meta['handler_name'][0];
        $return['video_pix_fmt'] = $pixfmt;
        $return['video_aspect_ratio'] = $sarValue;
        $return['video_aspect_v'] = $sarWH[0];
        $return['video_aspect_h'] = $sarWH[1];
        $return['audio_codename'] = $acodename;
        $return['audio_rate'] = $hz;
        $return['audio_fmt'] = $audioInfo[3];
        $return['audio_handler_name'] = $meta['handler_name'][1];
        return $return;
    }

    public static function videoTime($time)
    {
        if(is_numeric($time)) {
            $res = calThreshold($time, [3600, 60]);
            array_walk($res, function (&$v) {
                $v = str_pad($v, 2, 0, STR_PAD_LEFT);
            });
            $str = join(':', $res);
            $res[] = str_pad(getDecimal($time), 3, 0);
            return $str . ".{$res[3]}";
        } else {
            $res = explode(':', $time);

            $len = count($res);
            if($len >= 3) {
                $res[0] = $res[0] * 3600;
                $res[1] = $res[1] * 60;
            } else {
                $res[0] = $res[0] * 60;
            }
            return array_sum($res);
        }
    }

    public static function calThreshold($number, $threshold, $sep = '')
    {
        $res = [];
        $int = floor($number);
        foreach($threshold as $val) {
            if($int >= $val) {
                $res[] = floor($int / $val);
                $int = $int % $val;
            } else {
                $res[] = 0;
            }
        }
        $res[] = $int;
        return strlen($sep) > 0 ? join($sep, $res) : $res;
    }

}
