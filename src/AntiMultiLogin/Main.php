<?php
namespace AntiMultiLogin;

use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\Player;

class Main extends PluginBase implements Listener
{

    /**
     * @var array<string, int>
     */
    private $ipCount = [];
    /**
     * @var array<int|string, int>
     */
    private $cidCount = [];
    private $maxIpConnections;
    private $maxCidConnections;
    private $kickMessage;
    private $whitelist;

    public function onEnable()
    {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $config = $this->getConfig();

        $this->maxIpConnections = $config->get("max_ip_connections");
        $this->maxCidConnections = $config->get("max_cid_connections");
        $this->kickMessage = $config->get("kick_message");
        $this->whitelist = (array) $config->get("whitelist");

        // 重新记录在线玩家
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $message = $this->registerPlayer($player);
            if ($message !== false) { // 配置文件可能有改变
                $player->kick($message, false);
            }
        }
    }

    public function onPreLogin(PlayerPreLoginEvent $event)
    {
        $message = $this->registerPlayer($event->getPlayer());
        if ($message !== false) {
            $event->setKickMessage($message);
            $event->setCancelled(true);
        }
    }

    /**
     * 满足条件返回false，否则返回踢出提示
     * @param Player $player
     * @return array|bool|string
     */
    protected function registerPlayer(Player $player) {
        $ip = $player->getAddress();
        $cid = $player->getClientId();

        // 跳过验证
        if ($this->isWhitelisted($ip, $cid)) {
            return false;
        }

        // 统计IP连接数
        if (!isset($this->ipCount[$ip])) {
            $this->ipCount[$ip] = 0;
        }

        // 统计CID连接数
        if (!isset($this->cidCount[$cid])) {
            $this->cidCount[$cid] = 0;
        }

        $kick = false;

        // 判断白名单没有忽略IP并超过最大连接数量
        if ((!isset($this->whitelist["ip$ip"]) or $this->whitelist["ip$ip"] != "ignore") and
            ++$this->ipCount[$ip] > $this->maxIpConnections) {
            $kick = true;
        }

        // 判断白名单没有忽略CID并超过最大连接数量
        if ((!isset($this->whitelist["cid$cid"]) or $this->whitelist["cid$cid"] != "ignore") and
            ++$this->cidCount[$cid] > $this->maxCidConnections) {
            $kick = true;
        }

        if ($kick) {
            $message = str_replace(
                ["{ip_limit}", "{cid_limit}"],
                [$this->maxIpConnections, $this->maxCidConnections],
                $this->kickMessage
            );
            // 不能在这减少计数
            return $message;
        }
        return false;
    }

    protected function isWhitelisted(string $ip, $cid) {
        return isset($this->whitelist["ip$ip"]) and $this->whitelist["ip$ip"] == "pass" or
            isset($this->whitelist["cid$cid"]) and $this->whitelist["cid$cid"] == "pass";
    }

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        $ip = $player->getAddress();
        $cid = $player->getClientId();

        if ($this->isWhitelisted($ip, $cid)) {
            return;
        }

        if (isset($this->ipCount[$ip])) {
            if (--$this->ipCount[$ip] <= 0) {
                unset($this->ipCount[$ip]);
            }
        }

        if (isset($this->cidCount[$cid])) {
            if (--$this->cidCount[$cid] <= 0) {
                unset($this->cidCount[$cid]);
            }
        }
    }

    public function onDisable()
    {
        $this->ipCount = [];
        $this->cidCount = [];
    }
}
