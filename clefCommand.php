<?php
/**
 * @name CLEF
 * @version 0.19
 * @link http://www.yiiframework.com/extension/clef
 * 
 * @author eval <zamanfoo@gmail.com>
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */

class ClefCommand extends CConsoleCommand {
    
    /**
     * Class constants
     */
    const CLEF_USERAGENT = 'CLEF/0.19 (Yii Command Line Extension Finder)';
    const YII_BASE_URL = 'http://www.yiiframework.com';

    public function actionIndex() {
        print $this->getHelp();
    }
    
    /**
     * Public methods
     */
    public function actionDownload($extension) {
        if(!isset($extension)) {
            print "You must supply an argument to this command. Try running \"./yiic clef\" for usage information. \n";
            return;
        }
        
        $reqExt = $extension;

        $client = $this->_getClient();
        curl_setopt($client, CURLOPT_URL, $this->_getExtHomepage($reqExt));
        $tuData = curl_exec($client);

        if (curl_errno($client)) {
            echo 'Curl error: ' . curl_error($client);
            return;
        }

        curl_close($client);

        $matches = array();
        preg_match_all("/<ul class=\"g-list-none\">.*?<li>&raquo; <a title=\"(.*?)\" href=\"(.*?)\">(.*?)<\/a><\/li>/ism", $tuData, $matches);

        $descr = $matches[1][0];
        $targetUrl = $matches[2][0];
        $filename = $matches[3][0];

        $fp = fopen($filename, 'wb+');

        $client = $this->_getClient();
        curl_setopt($client, CURLOPT_URL, self::YII_BASE_URL . $targetUrl);
        curl_setopt($client, CURLOPT_TIMEOUT, 50);
        curl_setopt($client, CURLOPT_FILE, $fp);
        curl_setopt($client, CURLOPT_FOLLOWLOCATION, true);

        print "\n" . $this->_beautyTime() . "Starting download of " . $filename . " ..\n";
        curl_exec($client);
        if (!curl_errno($client)) {
            $info = curl_getinfo($client);
        } else {
            echo 'Curl error: ' . curl_error($client);
        }
        curl_close($client);
        fclose($fp);

        print $this->_beautyTime() . "Download finished for " . $filename . " (" . $info['size_download'] . " bytes in " . $info['total_time'] . " seconds).\n";
    }

    public function actionInfo($extension, $type='simple') {
        if(!isset($extension)) {
            print "You must supply an argument to this command. Try running \"./yiic clef\" for usage information. \n";
            return;
        }
        
        $searchFor = $extension;
        
        $client = $this->_getClient();
        curl_setopt($client, CURLOPT_URL, $this->_getExtHomepage($searchFor));
        $tuData = curl_exec($client);
        
        if (curl_errno($client)) {
            echo 'Curl error: ' . curl_error($client);
            return;
        }
        curl_close($client);

        $matches = array();
        preg_match_all("/<div class=\"content g-markdown\">.*?<p>(.*?)<\/p>\s+<h\d/ism", $tuData, $matches);


        print "\n--------------------------------------------------------------\n";
        //if($type == 'extended') print_r($extended);
        print strip_tags($matches[1][0]);
        print "\n--------------------------------------------------------------\n";
    }

    public function actionList($type) {
        if(!isset($type)) {
            print "You must supply an argument to this command. Try running \"./yiic clef\" for usage information. \n";
            return;
        }
        
        switch($type) {
            case 'top-rated':
                $targetUrl = $this->_getListPage('?sort=rating.desc');
                break;
            case 'newest':
                $targetUrl = $this->_getListPage('?sort=update.desc');
                break;
            case 'top-commented':
                $targetUrl = $this->_getListPage('?sort=comments.desc');
                break;
            case 'top-downloaded':
                $targetUrl = $this->_getListPage('?sort=downloads.desc');
                break;
            default:
                print "Unknown argument supplied. Exiting.\n";
                return;
        }
        
        $client = $this->_getClient();
        curl_setopt($client, CURLOPT_URL, $targetUrl);
        $tuData = curl_exec($client);

        if (curl_errno($client)) {
            echo 'Curl error: ' . curl_error($client);
            return;
        }

        curl_close($client);

        $matches = array();
        preg_match_all("/<div class=\"item\">.*?<h3 class=\"title\"><a href=\".*?\">([\w-]+)?<\/a><\/h3>.*?teaser\">(.*?)<\/div>.*?By <a href=\"\/user\/\d+\/\">(.*?)<\/a>/ism", $tuData, $matches);
        
        $total = count($matches[0]);
        $extensions = array();
        for ($i = 0; $i < $total; $i++) {
            $extensions[$i]['name'] = $matches[1][$i];
            $extensions[$i]['shortInfo'] = preg_replace("/\s+/m", " ", strip_tags($matches[2][$i]));
            $extensions[$i]['author'] = $matches[3][$i];
        }
        
        foreach ($extensions as $ext) {
            print $ext['name'] . " - " . $ext['shortInfo'] . " (by " . $ext['author'] . ")\n";
        }        
        
    }
    public function actionListcategory() {
        
        $categories = array(
            'Auth' => 1,
            'Caching' => 2,
            'Console' => 3,
            'Database' => 4,
            'Date & Time' => 5,
            'Error Handling' => 6,
            'File System' => 7,
            'Logging' => 8,
            'Mail' => 9,
            'Networking' => 10,
            'Security' => 11,
            'User Interface' => 15,
            'Validation' => 12,
            'Web Service' => 13,
            'Others' => 14,
        );
        
        print "\nSelect one of the following categories:\n";
        
        $cnt = 0;
        foreach($categories as $name => $val) {
            $cnt++;
            print "[" . $val . "]:" . $name;
            print ($cnt % 3 !== 0)? "\t\t" : "\n";
        }
        
        do {
            if(isset($selected)) print "Unknown selection. Please select a category based on the above matrix.\n";
            print "\nWhich category do you want to browse?: ";
            $selected = trim(fgets(STDIN));
        } while (array_search($selected, $categories) === false);
        
        $categoryNameAr = array_keys($categories, $selected);
        print "[" . $categoryNameAr[0] . "] selected. Initiating search..\n";
        
        $client = $this->_getClient();
        curl_setopt($client, CURLOPT_URL, $this->_getCategoryPage($selected));
        $tuData = curl_exec($client);

        if (curl_errno($client)) {
            echo 'Curl error: ' . curl_error($client);
            return;
        }

        curl_close($client);
        
        $matches = array();
        preg_match_all("/<div class=\"item\">.*?<h3 class=\"title\"><a href=\".*?\">([\w-]+)?<\/a><\/h3>.*?teaser\">(.*?)<\/div>.*?By <a href=\"\/user\/\d+\/\">(.*?)<\/a>/ism", $tuData, $matches);
        
        $total = count($matches[0]);
        $extensions = array();
        for ($i = 0; $i < $total; $i++) {
            $extensions[$i]['name'] = $matches[1][$i];
            $extensions[$i]['shortInfo'] = preg_replace("/\s+/m", " ", strip_tags($matches[2][$i]));
            $extensions[$i]['author'] = $matches[3][$i];
        }
        
        foreach ($extensions as $ext) {
            print $ext['name'] . " - " . $ext['shortInfo'] . " (by " . $ext['author'] . ")\n";
        }           
        
    }
    
    public function actionSearch($query) {
        if(!isset($query)) {
            print "You must supply an argument to this command. Try running \"./yiic clef\" for usage information. \n";
            return;
        }
        
        $client = $this->_getClient();
        curl_setopt($client, CURLOPT_URL, $this->_getQueryPage($query));
        $tuData = curl_exec($client);

        if (curl_errno($client)) {
            echo 'Curl error: ' . curl_error($client);
            return;
        }

        curl_close($client);

        $matches = array();
        preg_match_all("/<h2><a href=\"\/extension\/(.*)?\">(.*)?<\/a><\/h2>.*?<span class=\"author\">by <a href=\"\/user\/\d+\">(.*)?<\/a><\/span>.*?(<span class=\"g-vote plus\" title=\"(\d+) votes up\">\+\d+<\/span>)( \/ <span class=\"g-vote minus\" title=\"(\d+) votes down\">-\d+<\/span>)?/ism", $tuData, $matches);
                         
        $total = count($matches[1]);
        $extensions = array();
        for ($i = 0; $i < $total; $i++) {
            $extensions[$i]['votes'] = $matches[5][$i] - $matches[7][$i];
            $extensions[$i]['name'] = $matches[1][$i];
            $extensions[$i]['shortInfo'] = strip_tags($matches[2][$i]);
            $extensions[$i]['author'] = $matches[3][$i];
        }
        arsort($extensions);
        foreach ($extensions as $ext) {
            print "[" . $ext['votes'] . "] " . $ext['name'] . " - " . $ext['shortInfo'] . " (by " . $ext['author'] . ")\n";
        }

        print "\n" . $this->_beautyTime() . "--------------------------------------------------------------\n";
        print $this->_beautyTime() . "A total of " . count($matches[2]) . " extensions have been found.\n";
    }

    public function getHelp() {
        return <<<EOD
USAGE
  yiic clef [action] [parameter]

DESCRIPTION
  This command provides a CLI interface to the official Yii Extensions
  repository. You can use this command to search, browse & download
  any extension avaliable to the website. The action parameter can 
  be used to configure the task you want to perform and can take the
  following values: search, info, download.
  Each of the above actions takes different parameters. Their usage can 
  be found in the following examples.

EXAMPLES
 * yiic clef search --query=[query-string]
   Searches through Yii Extension Repository Search for every extension
   matching this term and returns a list with extension names followed by
   a short description.
   
 * yiic  clef info --extension=[extension-name] --type=[simple|extended]
   Returns information for the selected extension. (Note: extended is not
   yet implemented)
   
 * yiic clef download --extension=[extension-name]
   Downloads the latest version of the requested extension to the current
   directory.

* yiic clef list --type=[option]
   Lists top extensions by type based on one of the following options:
   - top-rated
   - newest
   - top-commented
   - top-downloaded

EOD;
    }

    /*
     * Private methods
     */
    private function _getClient() {
        $client = curl_init();
        curl_setopt($client, CURLOPT_USERAGENT, self::CLEF_USERAGENT);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        return $client;
    }
    
    private function _getListPage($criteria) {
        return self::YII_BASE_URL . "/extensions/" . $criteria;
    }
    
    private function _getCategoryPage($criteria) {
        return self::YII_BASE_URL . "/extensions/?category=" . $criteria;
    }
    
    private function _getExtHomepage($extName) {
        return self::YII_BASE_URL . "/extension/" . $extName;
    }
    
    private function _getQueryPage($query) {
        return self::YII_BASE_URL . '/search?q=' . $query . '&type=extension';
    }
    
    private function _beautyTime() {
        return "[" . date('H:i:s', time()) . "] ";
    }

    
    
    
}

