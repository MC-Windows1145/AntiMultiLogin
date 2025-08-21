<?php
namespace AntiMultiLogin;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\utils\Config;
use pocketmine\Player;

class Main extends PluginBase implements Listener
{

    private $ipCount = [];
    private $cidCount = [];
    private $maxIpConnections;
    private $maxCidConnections;

    public function onEnable()
    {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML, [
            "max_ip_connections" => 3,
            "max_cid_connections" => 2,
            "kick_message" => "§c我操你妈开小号的死妈伪人" //§c每个IP最多允许{ip_limit}个连接，每个设备最多允许{cid_limit}个连接
        ]);

        $this->maxIpConnections = $config->get("max_ip_connections");
        $this->maxCidConnections = $config->get("max_cid_connections");
        $this->kickMessage = $config->get("kick_message");
    }

    public function onPreLogin(PlayerPreLoginEvent $event)
    {
        $player = $event->getPlayer();
        $ip = $player->getAddress();
        $cid = $player->getClientId();

        // 统计IP连接数
        if (!isset($this->ipCount[$ip])) {
            $this->ipCount[$ip] = 0;
        }
        $this->ipCount[$ip]++;

        // 统计CID连接数
        if (!isset($this->cidCount[$cid])) {
            $this->cidCount[$cid] = 0;
        }
        $this->cidCount[$cid]++;

        // 检查限制
        $kick = false;
        if ($this->ipCount[$ip] > $this->maxIpConnections) {
            $kick = true;
        }
        if ($this->cidCount[$cid] > $this->maxCidConnections) {
            $kick = true;
        }

        if ($kick) {
            $message = str_replace(
                ["{ip_limit}", "{cid_limit}"],
                [$this->maxIpConnections, $this->maxCidConnections],
                $this->kickMessage
            );
            $event->setKickMessage($message);
            $event->setCancelled(true);


            if ($this->ipCount[$ip] <= 0)
                unset($this->ipCount[$ip]);
            if ($this->cidCount[$cid] <= 0)
                unset($this->cidCount[$cid]);
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        $ip = $player->getAddress();
        $cid = $player->getClientId();
        // 减少计数（因为连接被拒绝）
        $this->ipCount[$ip]--;
        $this->cidCount[$cid]--;
    }

    public function onDisable()
    {
        $this->ipCount = [];
        $this->cidCount = [];
    }
}
