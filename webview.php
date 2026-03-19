<?php

class webview
{
    private $gio;
    private $gobject;
    private $gtk;
    private $webkit;
    private $webview;
    private $glib;
    private $gdk;
    private $wvpid = 0;
    private $epid = 0;
    public $html;
    public $title;
    public $baseUrl;
    const GTK_WINDOW_TOPLEVEL = 0;
    const GIO = 'libgio-2';
    const GOBJECT = 'libgobject-2';
    const GTK = 'libgtk-4';
    const WEBKITGTK = 'libwebkitgtk-6';
    const GLB = 'libglib-2';
    const GDK = 'libgdk-3';
    const GTK_LIBS = [self::GOBJECT => 'err', self::GIO => 'err', self::GTK => 'err', self::WEBKITGTK => 'err', self::GLB => 'err', self::GDK => 'err'];
    static public $ins;
    public function __construct($title = '', $baseUrl = '')
    {
        $this->initFFI();
        $this->title = $title;
        $this->baseUrl = $baseUrl;
        self::$ins = $this;
        $this->process();
    }

    public function process()
    {
        list($mc, $wc) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        list($ws, $es) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        do {
            if ($this->wvpid === 0) {
                $this->wvpid = pcntl_fork();
                if ($this->wvpid === 0) {
                    $this->main($wc, $ws);
                    exit;
                }
            }
            if ($this->epid === 0) {
                $this->epid = pcntl_fork();
                if ($this->epid === 0) {
                    ob_start($this->appendHtml(...));
                    return;
                }
            }

            $pid = pcntl_wait($status);
            if ($pid == $this->wvpid) {
                $this->wvpid = 0;
            } elseif ($pid == $this->epid) {
                $this->epid = -1;
            }
        } while (true);
        exit;
    }


    public function msg($msg)
    {
        fwrite(STDERR, "\n$msg\n");
    }

    public function main()
    {
        $this->msg(__METHOD__);
        putenv("WEBKIT_DISABLE_SANDBOX_THIS_IS_DANGEROUS=1");
        putenv("WEBKIT_DISABLE_VBLANK_MONITOR=1");
        $app = $this->gtk_application_new("com.example.webkitgtk", 0);

        $this->g_signal_connect($app, "activate", $this->activate(...));
        $this->g_signal_connect_after($app, "", $this->activate(...));

        $status = $this->gio->g_application_run($app, 0, null);
        $this->msg('app return');
        $this->gobject->g_object_unref($app);
        $completed = 0;
        $context = $this->glib->g_main_context_default();
        $this->glib->g_idle_add(function () use (&$completed) {
            $completed = 1;
            return false;
        }, null);
        while (!$completed) {
            if ($this->glib->g_main_context_iteration($context, true)) {
                break;
            }
        }
    }

    public function __destruct()
    {
        $this->msg(__METHOD__);
    }

    public function shutdown()
    {
        $this->msg(__METHOD__);
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
        var_dump('context-menu');
        $length = $this->webkit->webkit_context_menu_get_n_items($menu);
        for ($i = 0; $i < $length; $i++) {
            $item = $this->webkit->webkit_context_menu_get_item_at_position($menu, $i);
            if ($this->webkit->webkit_context_menu_item_is_separator($item)) {
                continue;
            }
            $stockaction = $this->webkit->webkit_context_menu_item_get_stock_action($item);
            if ($stockaction == $this->webkit->WEBKIT_CONTEXT_MENU_ACTION_RELOAD) {
                $this->webkit->webkit_context_menu_remove($menu, $item);
                $action = $this->gio->g_simple_action_new('php-reload', null);
                $this->g_signal_connect($action, 'activate', function () {
                    var_dump("php reload");
                });
                $item = $this->webkit->webkit_context_menu_item_new_from_gaction($action, '重新载入', null);
                $this->webkit->webkit_context_menu_append($menu, $item);
            }
        }

        $action = $this->gio->g_simple_action_new('php-exit', null);
        $this->g_signal_connect($action, 'activate', function ($action, $param, $app) {
            $this->gobject->g_signal_emit_by_name($this->webview, 'destroy');
            $this->gio->g_application_quit($app);
        }, $app);
        $item = $this->webkit->webkit_context_menu_item_new_from_gaction($action, '退出', null);
        $this->webkit->webkit_context_menu_prepend($menu, $item);

        return null;
    }

    public function activate($app, $user_data)
    {
        $window = $this->gtk_application_window_new($app);

        $this->gtk_window_set_title($window, $this->title);
        $this->gtk_window_set_default_size($window, 1024, 768);

        $scrolled_window = $this->gtk_scrolled_window_new();
        $this->gtk_scrolled_window_set_policy($scrolled_window, 1, 1);

        // 创建 WebView
        $this->webview = $this->webkit->webkit_web_view_new();
        // 加载网页
        $this->webkit->webkit_web_view_load_html($this->webview, $this->html, $this->baseUrl);

        $this->g_signal_connect($this->webview, 'context-menu', $this->webviewContextMenu(...), $app);
        $this->gtk_scrolled_window_set_child($scrolled_window, $this->webview);
        $this->gtk_window_set_child($window, $scrolled_window);
        $this->gtk_window_present($window);
    }

    public function appendHtml($html, $phase)
    {
        $this->html .= $html;
        if ($phase & PHP_OUTPUT_HANDLER_FINAL) {

        }
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
        typedef int (*GCallback)(void *p1, void *p2, void *p3, void *p4);
        typedef void (*GClosureNotify)(void *data, void *closure);
        void g_object_unref(void *object);
        void g_signal_handler_disconnect (void* instance,gulong handler_id);
        gulong g_signal_connect_data(void *instance,const char *detailed_signal,GCallback c_handler,void *data,GClosureNotify destroy_data,int connect_flags);
        void g_clear_object (void** object_ptr);
        void g_signal_emit_by_name(void* ins, const char* signal);
        ',
            $lib[self::GOBJECT]
        );

        // $this->gdk = FFI::cdef('typedef enum {GDK_DELETE,GDK_MOTION_NOTIFY,GDK_BUTTON_PRESS,GDK_BUTTON_RELEASE,GDK_KEY_PRESS,GDK_KEY_RELEASE,GDK_ENTER_NOTIFY,GDK_LEAVE_NOTIFY,GDK_FOCUS_CHANGE,GDK_PROXIMITY_IN,GDK_PROXIMITY_OUT,GDK_DRAG_ENTER,GDK_DRAG_LEAVE,GDK_DRAG_MOTION,GDK_DROP_START,GDK_SCROLL,GDK_GRAB_BROKEN,GDK_TOUCH_BEGIN,GDK_TOUCH_UPDATE,GDK_TOUCH_END,GDK_TOUCH_CANCEL,GDK_TOUCHPAD_SWIPE,GDK_TOUCHPAD_PINCH,GDK_PAD_BUTTON_PRESS,GDK_PAD_BUTTON_RELEASE,GDK_PAD_RING,GDK_PAD_STRIP,GDK_PAD_GROUP_MODE,GDK_TOUCHPAD_HOLD,GDK_PAD_DIAL,GDK_EVENT_LAST} GdkEventType;
        // int gdk_event_get_event_type (const void* event);
        // int gdk_event_get_button (const void* event,unsigned int* button);
        // ', $lib[self::GDK]);

        $this->gio = FFI::cdef(
            'int g_application_run(void *application, int argc, char **argv);
            void g_application_quit (void* application);
            const void* g_action_get_state_type (void* action);
            const char* g_action_get_name (void* action);
            void* g_action_get_state (void* action);
            int g_variant_type_is_basic (const void* type);
            void g_simple_action_set_enabled (void* simple,int enabled);
            void* g_simple_action_new ( const char* name,const void* parameter_type);
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
