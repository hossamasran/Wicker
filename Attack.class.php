<?php
/*
status
0 = null
1 = cracking
2 = failed
3 = success
4 = terminated
5 = paused
*/
require_once("Wicker.php");

Class Attack
{
    private $id;
    private $cap_id;
    private $attack;
    private $status;
    private $status_text;
    private $password;
    private $tmpfile;
    private $runtime;
    private $current;
    private $rate;
    private $auth;
    private $pid;
    private $name = array("10k top passwords (fixed)", "rockyou", "small", "Custom-WPA", "Super-WPA", "big", "bigger", "combined", "eight");
    private $size = array(4139, 9605415, 46836902, 109605684, 582620889, 648418381, 915103913, 1623275482, 360747463);
    private $statusName = array("-", "<font color='purple'>Cracking</font>", "<font color='red'>Failed</font>", "<font color='green'>Success</font>", "<font color='orange'>Terminated</font>", "<font color='blue'>Paused</font>");

    public $db;

    public static function fromDB($id, $attack = 1) {
        global $wicker;
        $instance = new self();
        $instance->connectToDatabase();

        $statement = $wicker->db->con()->prepare("SELECT * FROM attacks WHERE cap_id = ? AND attack = ?");
        $statement->execute(array($id, $attack));
        $info = $statement->fetchObject();

        $instance->id          = $info->id;
        $instance->cap_id      = $info->cap_id;
        $instance->attack      = $info->attack;
        $instance->status      = $info->status;
        $instance->status_text = $info->status_text;
        $instance->password    = $info->password;
        $instance->tmpfile     = $info->tmpfile;
        $instance->runtime     = $info->runtime;
        $instance->current     = $info->current;
        $instance->rate        = $info->rate;
        $instance->auth        = $info->auth;
        $instance->pid         = $info->pid;

        return $instance;
    }

    private function connectToDatabase() {
        $database = new Database;
        $this->db = $database;
    }

    private function setVal($field, $val) {
        $statement = $this->db->con()->prepare("UPDATE `attacks` SET `$field` = ? WHERE `id` = ?");
        $statement->execute(array($val, $this->getID()));
    }

    public function updateData() {
        if ($this->getStatus() != 2 && $this->getStatus() != 3) {
            $this->calculateCurrentKey();
            $this->updateStatus();
            $this->updateRuntime();
            $this->calculateRate();
        }
    }

    private function updateStatus() {
        exec("ps " . $this->getPID(), $status);
        if (isset($status[1])) {
            $this->setStatusText("<font color='green'>Yes</font>");
        } else {
            $this->setStatusText("<font color='red'>No</font>");
        }

        // Update status code to failed or success if cracked once the PID isn't running anymore
        $this->wasCracked();
    }

    private function wasCracked() {
        global $wicker;
        $log = file_get_contents("logs/" . $this->getTmpfile());
        $keys = $wicker->extractData(substr($log, -1500), "The password is '", "'.");
        if ($keys != null) {
            $this->setStatus(3);
            $this->setPassword($keys);
        } else {
            if ($this->getStatus() == 1 && $this->getStatusText() == "<font color='red'>No</font>") {
                $this->setStatus(2);
                $this->setCurrent($this->size[$this->getAttack()-1]);
                $this->setPassword("");
            }
        }
    }

    private function updateRuntime() {
        exec("ps -o time " . $this->getPID(), $runtime);
        if (isset($runtime[1])) {
            $ex = explode(":", $runtime[1]);
            $secs = $ex[0]*3600 + $ex[1]*60 + $ex[2];
            $this->setRuntime(gmdate("H:i:s", round($secs/4, 0)));
        } else {
            if ($this->getStatus() == 0) {
                $this->setRuntime("00:00:00");
            }
        }
    }

    public function terminate() {
        $this->updateData();
        $this->setStatus(4);
        posix_kill($this->getPID(), 9);
    }

    public function calculateCurrentKey() {
        global $wicker;
        $log = file_get_contents("logs/" . $this->getTmpfile());
        $keys = $wicker->extractData(substr($log, -1500), "Tried ", " PMKs so");
        $current_keys = $keys[count($keys)-1];
        $this->setCurrent($current_keys);
    }

    public function calculateRate() {
        global $wicker;
        $log = file_get_contents("logs/" . $this->getTmpfile());
        $rate = $wicker->extractData(substr($log, -1500), "far; ", " PMKs per");
        $rate = $rate[count($rate)-1];

        if($this->getStatus() == 1)
            $this->setRate($rate);
        else
            $this->setRate(0);
    }

    public function getID() { return $this->id; }
    public function getCapID() { return $this->cap_id; }
    public function getAttack() { return $this->attack; }
    public function getStatus() { return $this->status; }
    public function getStatusText() { return $this->status_text; }
    public function getPassword() { return CapFile::fromDB($this->cap_id)->getPassword(); }
    public function getTmpfile() { return $this->tmpfile; }
    public function getRuntime() { return $this->runtime; }
    public function getAttackName() { return $this->name[$this->attack-1]; }
    public function getDictionarySize() { return $this->size[$this->attack-1]; }
    public function getCurrent() { return $this->current; }
    public function getRate() { return $this->rate; }
    public function getAuth() { return $this->auth; }
    public function getPID() { return $this->pid; }

    public function setAuth($val) { $this->setVal("auth", $val); $this->auth = $val; }
    public function setRate($val) { $this->setVal("rate", $val); $this->rate = $val; }
    public function setCurrent($val) { $this->setVal("current", $val); $this->current = $val; }
    public function setRuntime($val) { $this->setVal("runtime", $val); $this->runtime = $val; }
    public function setTmpfile($val) { $this->setVal("tmpfile", $val); $this->tmpfile = $val; }
    public function setStatus($val) { $this->setVal("status", $val); $this->status = $val; }
    public function setStatusText($val) { $this->setVal("status_text", $val); $this->status_text = $val; }
    public function setPassword($val) { CapFile::fromDB($this->cap_id)->setPassword($val); }
    public function setPID($val) { $this->setVal("pid", $val); $this->pid = $val; }

    public function getStatusName() { return $this->statusName[$this->getStatus()]; }
}
