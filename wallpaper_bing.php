<?php
/**
 * Wallpaper from bing
 *
 * Sets the background image of the login/logoff page to a daily bing image.
 *
 * This plugin requires that a working public_ldap directory be configured.
 *
 * @license GNU GPLv3+
 */
class wallpaper_bing extends rcube_plugin
{
    public $task = '(login|logout)';

    // we've got no ajax handlers
    // necessary?
    public $noajax = true;


    /**
     * Plugin initialization. API hooks binding.
     */
    public function init()
    {
        $this->load_config();

        // can't we get this nicer
        $this->plugindir = RCUBE_PLUGINS_DIR . 'wallpaper_bing/';
        $this->browserlang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 5);
        $this->cssfile = 'custom-'.$this->browserlang.'.css';
        $this->imgfile = $this->browserlang.'.jpg';

        $this->add_hook('startup', [$this, 'startup']);

    }

    /**
     * 'startup' hook handler.
     */
    public function startup($args)
    {
        $rcmail = rcmail::get_instance();

        //better way / location of 'enabling' the plugin functionality
        if (!($rcmail->config->get('bing'))) {
            return;
        }

        $expsec = $rcmail->config->get('expirehours') * 60 * 60;

        if (!file_exists($this->cssfile)) {
            $this->createcss($this->cssfile, $this->browserlang);
        }

        // download the background if it not exists
        if (!file_exists($this->plugindir . $this->imgfile)) {
            $this->getbingimage();
        }

        // download the background if the image has expired
        $filetime = filectime($this->plugindir . $this->imgfile);
        if ((time() - $expsec) > $filetime) {
            $this->getbingimage();
        }

        if ($args['task'] == "login" or $args['task'] == "logout") {
            $this->add_hook('render_page', array($this, 'render_page'));
        }
    }

    /**
     * insert the custom css.
     */
    public function render_page($p)
    {
        $this->include_stylesheet($this->cssfile);
    }

    private function createcss($file, $locale)
    {
        $data = "body {\r\n" .
        "  background-image: url('".$locale.".jpg');\r\n"  .
        "  background-size: cover;\r\n" .
        "  background-repeat: no-repeat;\r\n}\r\n" .
        "#layout-content {\r\n" .
        "  background-color: unset;\r\n}\r\n";

        $opacity = "img\r\n{\r\n" .
        "  opacity: 0.5;\r\n}";

        file_put_contents($this->plugindir . $file, $data);
    }

    private function getbingimage()
    {

        $rcmail = rcmail::get_instance();

        $bingurl = 'https://cn.bing.com/HPImageArchive.aspx';

        $res = $rcmail->config->get('resolution', 1920);
        $res = 1366;

        $bingurl .= '?format=js&idx=0&n=1&mkt='.$this->browserlang;

        $tmpfile = tempnam($this->plugindir, 'bing');
        $imgfile = $this->plugindir . $this->imgfile;
        $jsonstr = $this->get_web_page($bingurl);
        $data = json_decode($jsonstr);
        $imgurl = 'https://cn.bing.com'.$data->{"images"}[0]->{"url"};
        file_put_contents($tmpfile, $this->get_web_page($imgurl));

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type = finfo_file($finfo, $tmpfile);
        if (isset($type) && in_array($type, array("image/jpeg", "image/jpg"))) {
            rename($tmpfile, $imgfile);
        }
    }

    private function get_web_page($url, $cookiesIn = '')
    {
        $options = array(
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0',
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,      // timeout on connect
            CURLOPT_TIMEOUT        => 2,      // timeout on response
            CURLOPT_MAXREDIRS      => 2       // stop after 10 redirects
        );

        $ch      = curl_init($url);
        curl_setopt_array($ch, $options);
        $result  = curl_exec($ch);
        $err     = curl_errno($ch);
        curl_close($ch);

        return $result;
    }
}
