<?php

// 运行实例，fork 10 个进程，每个进程输出一行 Im a worker ，并保存在 /tmp/forker.log 中：
// CLI命令： php Forker.php 10
/*
if (empty($argv[1])) {
    echo "Im a worker\n";
    sleep(10);
    exit();
} else {
    $forker = new Forker('/tmp/forker.log');
    $forker->fork($forker->findCommand('php') . ' ' . __FILE__, (int)$argv[1] <= 0 ? 10 : (int)$argv[1]);
}
*/

/**
 * Forker 可以让 php-cli 进程借助 nohup 以守护进程的方式运行。
 * 这个 Forker 仅仅是让进程成为守护进程，不会复制父进程的内存。
 */
class Forker {
    
    private $nohub = '/usr/bin/nohup';
    private $out   = '/tmp/forker.log';
    
    /**
     * @param string $output 输出文件的路径。进程的标准输出将重定向到此文件
     * @throws \RuntimeException
     */
    public function __construct($output = '') {
        if (false !== ($nohup = $this->findCommand('nohup'))) {
            $this->nohub = $nohup;
        }
        if (!is_executable($this->nohub)) {
            throw new \RuntimeException('nohup not excutable');
        }
        if ($output) {
            $this->setOutput($output);
        }
    }
    
    /**
     * 设置输出文件的路径。进程的标准输出将重定向到此文件
     * @param string $file
     * @return \Forker
     * @throws \RuntimeException
     */
    public function setOutput($file) {
        if (!is_file($file)) {
            $dir = dirname($file);
            if ((!is_dir($dir) && !mkdir($dir, 0755, true)) || !is_writable($dir)) {
                throw new \RuntimeException('output is not writable, can not create output');
            }
        } else if (!is_writable($file)) {
            throw new \RuntimeException('output is not writable');
        }
        $this->out = $file;
        return $this;
    }
    
    /**
     * 获取输出文件的路径
     * @return string
     */
    public function getOutput() {
        return $this->out;
    }
    
    /**
     * 执行命令
     * @param string $command 命令。命令中的文件参数需要使用绝对路径
     * @param int $forks fork的进程数
     */
    public function fork($command, $forks = 1) {
        for ($i = 0; $i < $forks; ++$i) {
            $this->execute($command);
        }
    }
    
    /**
     * 根据当前环境查找命令的绝对路径
     * @param string $name
     * @return boolean
     */
    public function findCommand($name) {
        $file = trim(exec("which {$name}"));
        if (is_file($file) && is_executable($file)) {
            return $file;
        }
        return false;
    }
    
    /**
     * 执行命令，成功返回 true，失败返回 false
     * @param string $command
     * @return boolean
     */
    private function execute($command) {
        $lines = [];
        $code  = 0;
        exec("{$this->nohub} {$command} >> {$this->out} 2>&1 &", $lines, $code);
        if (0 !== (int)$code) {
            file_put_contents($this->out, "fork {$command} FAILD[{$code}]:\n" . implode("\n", $lines) . "\n", FILE_APPEND);
            return false;
        }
        file_put_contents($this->out, "fork {$command} OK\n", FILE_APPEND);
        return true;
    }
    
}
