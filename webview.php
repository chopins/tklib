<?php

class webview
{
    private $gio;
    private $gobject;
    private $gtk;
    private $webkit;
    private $webview;
    private $glib;
    private $wvpid = 0;
    private $epid = 0;
    private $reportFp;
    public $html;
    public $title;
    public $baseUrl;
    const GTK_WINDOW_TOPLEVEL = 0;
    const GIO = 'libgio-2';
    const GOBJECT = 'libgobject-2';
    const GTK = 'libgtk-4';
    const WEBKITGTK = 'libwebkitgtk-6';
    const GLB = 'libglib-2';
    const GTK_LIBS = [self::GOBJECT => '2.0', self::GIO => '2.0', self::GTK => '4.0', self::WEBKITGTK => '6.0', self::GLB => '2.0'];
    const WEBVIEW_EXIT = 1;
    const EXEC_EXIT = 2;
    const EXEC_RELOAD = 3;
    const EXEC_REOPEN = 4;
    public static $DL_PATH_LIST = [self::GOBJECT => '', self::GIO => '', self::GTK => '', self::WEBKITGTK => '', self::GLB => ''];
    private static $reloadAction;
    private static $exitAction;
    private static $reopenAciton;

    public function __construct($argc, $argv = [])
    {
        $this->title = 'php webview';
        $this->baseUrl = 'file://' . getcwd() . '/';
        if(!function_exists('pcntl_fork')) {
            throw new RuntimeException('need php pcntl extension');
        }
        $this->process($argc, $argv);
    }

    public function process($argc, $argv)
    {
        list($mainFp, $reportFp) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        list($viewFp, $outFp) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        do {
            if ($this->wvpid === 0) {
                $this->wvpid = pcntl_fork();
                if ($this->wvpid === 0) {
                    $this->reportFp = $reportFp;
                    $this->main($viewFp);
                    fwrite($reportFp, self::WEBVIEW_EXIT);
                    usleep(1000);
                    exit;
                }
            }
            if ($this->epid === 0) {
                $this->epid = pcntl_fork();
                if ($this->epid === 0) {
                    register_shutdown_function(function () use ($reportFp) {
                        fwrite($reportFp, self::EXEC_EXIT);
                        usleep(1000);
                    });
                    ob_start(function ($buff, $pase) use ($outFp) {
                        if ($pase & PHP_OUTPUT_HANDLER_START) {
                            fwrite($outFp, pack('CJ', PHP_OUTPUT_HANDLER_START, 0));
                        }
                        $len = strlen($buff);
                        $data = pack('CJ', PHP_OUTPUT_HANDLER_WRITE, $len) . $buff;
                        fwrite($outFp, $data);
                        if ($pase & PHP_OUTPUT_HANDLER_FINAL) {
                            fwrite($outFp, pack('CJ', PHP_OUTPUT_HANDLER_FINAL, 0));
                        }
                    });
                    return;
                }
            }
            $reopen = false;
            do {
                $r = [$mainFp];
                $w = $e = null;
                if (stream_select($r, $w, $e, 0, 100000) > 0) {
                    $b = fread($r[0], 1);
                    if ($b == self::EXEC_EXIT) {
                        pcntl_waitpid($this->epid, $status);
                        $this->epid = -1;
                    } else if ($b == self::WEBVIEW_EXIT) {
                        pcntl_waitpid($this->wvpid, $status);
                        pcntl_waitpid($this->epid, $status);
                        if ($reopen) {
                            return $this->reopen($argc, $argv);
                        }
                        exit;
                    } else if ($b == self::EXEC_RELOAD) {
                        $this->epid = 0;
                        break;
                    } else if ($b == self::EXEC_REOPEN) {
                        $reopen = true;
                    }
                }
                usleep(10000);
            } while (true);
        } while (true);
        exit;
    }

    public function reopen($argc, $argv)
    {
        pcntl_exec(PHP_BINARY, $argv, getenv());
    }


    public function msg($msg)
    {
        $pid = getmypid();
        fwrite(STDERR, "\n$pid : $msg\n");
    }

    public function main($viewFp)
    {
        $this->initFFI();
        putenv("WEBKIT_DISABLE_SANDBOX_THIS_IS_DANGEROUS=1");
        putenv("WEBKIT_DISABLE_VBLANK_MONITOR=1");
        $app = $this->gtk_application_new("com.example.webkitgtk", 0);
        $this->html = '<htm><body>wait load html</body></html>';
        $this->g_signal_connect($app, "activate", $this->activate(...));
        $isShutdown = false;
        $this->glib->g_idle_add(function ($app) use ($viewFp, &$isShutdown) {
            if($isShutdown) {
                return 0;
            }
            $r = [$viewFp];
            $w = $e = null;
            $n = stream_select($r, $w, $e, 0, 1000);
            if ($n <= 0) {
                return 1;
            }
            $c = fread($r[0], 9);
            $d = unpack('Cstate/Jlen', $c);
            if ($d['state'] === PHP_OUTPUT_HANDLER_FINAL) {
                $this->webkit->webkit_web_view_load_html($this->webview, $this->html, $this->baseUrl);
                $this->html = '';
            }
            if ($d['state'] === PHP_OUTPUT_HANDLER_START) {
                $this->html = '';
            } elseif ($d['state'] === PHP_OUTPUT_HANDLER_WRITE) {
                $this->html .= stream_get_contents($r[0], $d['len']);
            }
            return 1;
        }, $app);
        $this->g_signal_connect($app, 'shutdown', function($app) use(&$isShutdown) {
            $isShutdown = true;
            //$this->gio->g_application_quit($app);
        });
        $status = $this->gio->g_application_run($app, 0, null);

        $this->gobject->g_object_unref($app);

        return $status;
    }

    public function run($app)
    {
        // $join = join("\0", $argv);
        // $len = strlen($join);
        // $str = $this->gio->new("char[$len]", false);
        // FFI::memcpy($str, $join, $len);
        // $p = FFI::addr($str[0]);
        return $this->gio->g_application_run($app, 0, null);
    }

    public function g_signal_connect($ins, $signal, $handler, $data = null)
    {
        return $this->gobject->g_signal_connect_data($ins, $signal, $handler, $data, null, 0);
    }
    public function g_signal_connect_after($ins, $signal, $handler, $data = null)
    {
        return $this->gobject->g_signal_connect_data($ins, $signal, $handler, $data, null, 1);
    }

    public function webviewContextMenu($webview, $menu, $hittest, $app)
    {
        $length = $this->webkit->webkit_context_menu_get_n_items($menu);
        for ($i = 0; $i < $length; $i++) {
            $item = $this->webkit->webkit_context_menu_get_item_at_position($menu, $i);
            if ($this->webkit->webkit_context_menu_item_is_separator($item)) {
                continue;
            }
            $stockaction = $this->webkit->webkit_context_menu_item_get_stock_action($item);
            if ($stockaction == $this->webkit->WEBKIT_CONTEXT_MENU_ACTION_RELOAD) {
                $this->webkit->webkit_context_menu_remove($menu, $item);
                $itemRL = $this->webkit->webkit_context_menu_item_new_from_gaction(self::$reloadAction, '刷新页面', null);
                $this->webkit->webkit_context_menu_append($menu, $itemRL);
            }
        }
        $itemEX = $this->webkit->webkit_context_menu_item_new_from_gaction(self::$exitAction, '退出', null);
        $this->webkit->webkit_context_menu_prepend($menu, $itemEX);

        $itemRO = $this->webkit->webkit_context_menu_item_new_from_gaction(self::$reopenAciton, '重新打开', null);
        $this->webkit->webkit_context_menu_append($menu, $itemRO);
        return null;
    }

    public function newPHPReopen()
    {
        self::$reopenAciton = $this->gio->g_simple_action_new('php-reopen', null);
        $this->g_signal_connect(self::$reopenAciton, 'activate', function () {
            fwrite($this->reportFp, self::EXEC_REOPEN);
            $this->gio->g_action_activate(self::$exitAction, null);
        });
    }

    public function newPHPReload()
    {
        self::$reloadAction = $this->gio->g_simple_action_new('php-reload', null);
        $this->g_signal_connect(self::$reloadAction, 'activate', function () {
            fwrite($this->reportFp, self::EXEC_RELOAD);
        });
    }

    public function newExitAction($app)
    {
        self::$exitAction = $this->gio->g_simple_action_new('php-exit', null);
        $this->g_signal_connect(self::$exitAction, 'activate', function ($action, $param, $app) {
            $this->gobject->g_signal_emit_by_name($this->webview, 'destroy');
            $this->gio->g_application_quit($app);
        }, $app);
    }

    public function activate($app, $user_data)
    {
        $window = $this->gtk_application_window_new($app);

        $this->gtk_window_set_title($window, $this->title);
        $this->gtk_window_set_default_size($window, 1024, 768);

        $scrolled_window = $this->gtk_scrolled_window_new();
        $this->gtk_scrolled_window_set_policy($scrolled_window, 1, 1);
        $this->newExitAction($app);
        $this->newPHPReload();
        $this->newPHPReopen();
        // 创建 WebView
        $this->webview = $this->webkit->webkit_web_view_new();
        // 加载网页
        $this->webkit->webkit_web_view_load_html($this->webview, $this->html, $this->baseUrl);
        $this->g_signal_connect($this->webview, 'decide-policy', $this->decidePolicy(...), null);

        $this->g_signal_connect($this->webview, 'context-menu', $this->webviewContextMenu(...), $app);
        $this->gtk_scrolled_window_set_child($scrolled_window, $this->webview);
        $this->gtk_window_set_child($window, $scrolled_window);
        $this->gtk_window_present($window);
    }

    public function decidePolicy($webview, $decision, $decision_type, $data)
    {
        if ($decision_type === null) {
            $type = 0;
        } else {
            $type = $this->gobject->cast('int*', FFI::addr($decision_type))[0];
        }
        switch ($type) {
            case 0: //WEBKIT_POLICY_DECISION_TYPE_NAVIGATION_ACTION
            case 1: //WEBKIT_POLICY_DECISION_TYPE_NEW_WINDOW_ACTION
                $action = $this->webkit->webkit_navigation_policy_decision_get_navigation_action($decision);
                $actionType = $this->webkit->webkit_navigation_action_get_navigation_type($action);
                if ($actionType == 3) { //WEBKIT_NAVIGATION_TYPE_RELOAD
                    $this->webkit->webkit_policy_decision_ignore($decision);
                    $this->gio->g_action_activate(self::$reloadAction, null);
                    return true;
                }
                break;
            case 2: //WEBKIT_POLICY_DECISION_TYPE_RESPONSE
                break;
        }
        return 0;
    }


    public function loadhtml($html = '')
    {
        if ($html) {
            $this->html = $html;
        }
        $this->webkit->webkit_web_view_load_html($this->webview, $this->html, $this->baseUrl);
    }

    public function findWebkitGTK(): array
    {
        $libpath = [];
        foreach (self::GTK_LIBS as $name => $errmsg) {
            $libpath[$name] = $this->findDLL($name, $errmsg);
        }
        return $libpath;
    }

    public function findDLL($name, $errmsg): string
    {
        if (self::$DL_PATH_LIST[$name]) {
            return self::$DL_PATH_LIST[$name];
        }
        $output = [];
        exec("ldconfig -p |grep $name", $output, $code);
        if ($code != 0) {
            throw new RuntimeException("cant not find $name DLL, $errmsg");
        }
        list(, $path) = explode('=>', $output[0]);
        return realpath(trim($path));
    }

    public function initFFI()
    {
        $lib = $this->findWebkitGTK();
        $this->glib = FFI::cdef('
        typedef int (* GSourceFunc) (void*user_data);
        typedef void(* GDestroyNotify) ( void* data);
        void* g_main_context_default();
        int g_main_context_iteration (void* context,int may_block);
        int g_main_context_pending(void* context);
        void g_main_context_dispatch (void* context);
        unsigned int g_idle_add_full (int priority,GSourceFunc f,void*data,GDestroyNotify notify);
        unsigned int g_idle_add (GSourceFunc function,void* data);
        int g_source_remove (unsigned int tag);
        ', $lib[self::GLB]);

        $this->gobject = FFI::cdef(
            'typedef unsigned long gulong;
        typedef unsigned long gsize;
        typedef int (*GCallback)(void *p1, void *p2, void* p3, void *p4);
        typedef void (*GClosureNotify)(void *data, void *closure);
        void g_object_unref(void *object);
        void g_signal_handler_disconnect (void* instance,gulong handler_id);
        gulong g_signal_connect_data(void *instance,const char *detailed_signal,GCallback c_handler,void *data,GClosureNotify destroy_data,int connect_flags);
        void g_clear_object (void** object_ptr);
        void g_signal_emit_by_name(void* ins, const char* signal);
        ',
            $lib[self::GOBJECT]
        );

        $this->gio = FFI::cdef(
            'int g_application_run(void *application, int argc, char **argv);
            void g_application_quit (void* application);
            const void* g_action_get_state_type (void* action);
            const char* g_action_get_name (void* action);
            void* g_action_get_state (void* action);
            int g_variant_type_is_basic (const void* type);
            void g_simple_action_set_enabled (void* simple,int enabled);
            void* g_simple_action_new ( const char* name,const void* parameter_type);
            void g_application_hold (void* application);
            void g_application_release (void* application);
            void g_action_activate (void* action, void* parameter);
            ',
            $lib[self::GIO]
        );

        $this->gtk = FFI::cdef(
            'void *gtk_application_new(const char *application_id, int flags);
        void *gtk_application_window_new(void *application);
        void gtk_window_set_title(void *window, const char *title);
        void gtk_window_set_default_size(void *window, int width, int height);
        void gtk_window_set_child(void *window, void *child);
        void gtk_widget_show(void *widget);
        void gtk_window_present (void* window);
        void* gtk_scrolled_window_new (void);
        void gtk_scrolled_window_set_policy (void* scrolled_window,int hscrollbar_policy,int vscrollbar_policy);
        void gtk_scrolled_window_set_child (void* scrolled_window,void* child);
        ',
            $lib[self::GTK]
        );

        $this->webkit = FFI::cdef(
            'const void* webkit_web_view_new();
            void webkit_web_view_load_html(void* web_view,const char* content,const char* base_uri);
            void webkit_web_view_try_close (void* web_view);
            void webkit_web_view_terminate_web_process (void* web_view);
            unsigned int webkit_context_menu_get_n_items(void* menu);
            void* webkit_context_menu_get_item_at_position (void* menu, unsigned int position);
            void* webkit_context_menu_item_get_gaction (void* item);
            int webkit_context_menu_item_get_stock_action(void* item);
            int webkit_context_menu_item_is_separator (void* item);
            void* webkit_context_menu_get_event (void* menu);
            void webkit_context_menu_remove (void* menu,void* item);
            void webkit_context_menu_append (void* menu,void* item);
            void webkit_context_menu_prepend(void* menu,void* item);
            void* webkit_context_menu_item_new_from_gaction (void* action,const char* label,void* target);
            void* webkit_navigation_policy_decision_get_navigation_action (void* decision);
            int webkit_navigation_action_get_navigation_type (void* navigation);
            void webkit_policy_decision_ignore (void* decision);

            typedef enum {WEBKIT_CONTEXT_MENU_ACTION_NO_ACTION,WEBKIT_CONTEXT_MENU_ACTION_OPEN_LINK,WEBKIT_CONTEXT_MENU_ACTION_OPEN_LINK_IN_NEW_WINDOW,WEBKIT_CONTEXT_MENU_ACTION_DOWNLOAD_LINK_TO_DISK,WEBKIT_CONTEXT_MENU_ACTION_COPY_LINK_TO_CLIPBOARD,WEBKIT_CONTEXT_MENU_ACTION_OPEN_IMAGE_IN_NEW_WINDOW,WEBKIT_CONTEXT_MENU_ACTION_DOWNLOAD_IMAGE_TO_DISK,WEBKIT_CONTEXT_MENU_ACTION_COPY_IMAGE_TO_CLIPBOARD,WEBKIT_CONTEXT_MENU_ACTION_COPY_IMAGE_URL_TO_CLIPBOARD,WEBKIT_CONTEXT_MENU_ACTION_OPEN_FRAME_IN_NEW_WINDOW,WEBKIT_CONTEXT_MENU_ACTION_GO_BACK,WEBKIT_CONTEXT_MENU_ACTION_GO_FORWARD,WEBKIT_CONTEXT_MENU_ACTION_STOP,WEBKIT_CONTEXT_MENU_ACTION_RELOAD,WEBKIT_CONTEXT_MENU_ACTION_COPY,WEBKIT_CONTEXT_MENU_ACTION_CUT,WEBKIT_CONTEXT_MENU_ACTION_PASTE,WEBKIT_CONTEXT_MENU_ACTION_DELETE,WEBKIT_CONTEXT_MENU_ACTION_SELECT_ALL,WEBKIT_CONTEXT_MENU_ACTION_INPUT_METHODS,WEBKIT_CONTEXT_MENU_ACTION_UNICODE,WEBKIT_CONTEXT_MENU_ACTION_SPELLING_GUESS,WEBKIT_CONTEXT_MENU_ACTION_NO_GUESSES_FOUND,WEBKIT_CONTEXT_MENU_ACTION_IGNORE_SPELLING,WEBKIT_CONTEXT_MENU_ACTION_LEARN_SPELLING,WEBKIT_CONTEXT_MENU_ACTION_IGNORE_GRAMMAR,WEBKIT_CONTEXT_MENU_ACTION_FONT_MENU,WEBKIT_CONTEXT_MENU_ACTION_BOLD,WEBKIT_CONTEXT_MENU_ACTION_ITALIC,WEBKIT_CONTEXT_MENU_ACTION_UNDERLINE,WEBKIT_CONTEXT_MENU_ACTION_OUTLINE,WEBKIT_CONTEXT_MENU_ACTION_INSPECT_ELEMENT,WEBKIT_CONTEXT_MENU_ACTION_OPEN_VIDEO_IN_NEW_WINDOW,WEBKIT_CONTEXT_MENU_ACTION_OPEN_AUDIO_IN_NEW_WINDOW,WEBKIT_CONTEXT_MENU_ACTION_COPY_VIDEO_LINK_TO_CLIPBOARD,WEBKIT_CONTEXT_MENU_ACTION_COPY_AUDIO_LINK_TO_CLIPBOARD,WEBKIT_CONTEXT_MENU_ACTION_TOGGLE_MEDIA_CONTROLS,WEBKIT_CONTEXT_MENU_ACTION_TOGGLE_MEDIA_LOOP,WEBKIT_CONTEXT_MENU_ACTION_ENTER_VIDEO_FULLSCREEN,WEBKIT_CONTEXT_MENU_ACTION_MEDIA_PLAY,WEBKIT_CONTEXT_MENU_ACTION_MEDIA_PAUSE,WEBKIT_CONTEXT_MENU_ACTION_MEDIA_MUTE,WEBKIT_CONTEXT_MENU_ACTION_DOWNLOAD_VIDEO_TO_DISK,WEBKIT_CONTEXT_MENU_ACTION_DOWNLOAD_AUDIO_TO_DISK,WEBKIT_CONTEXT_MENU_ACTION_INSERT_EMOJI,WEBKIT_CONTEXT_MENU_ACTION_PASTE_AS_PLAIN_TEXT,WEBKIT_CONTEXT_MENU_ACTION_CUSTOM} WebKitContextMenuAction;
            ',
            $lib[self::WEBKITGTK]
        );
    }

    public function __call($name, $arguments)
    {
        return $this->gtk->$name(...$arguments);
    }
}

new webview($argc, $argv);
