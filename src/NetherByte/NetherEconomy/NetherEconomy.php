<?php
namespace NetherByte\NetherEconomy;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use NetherByte\NetherEconomy\gui\BankGUI;
use NetherByte\NetherEconomy\libs\muqsit\invmenu\InvMenuHandler;
use pocketmine\command\PluginCommand;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;

class NetherEconomy extends PluginBase implements Listener {
    private Config $balanceConfig;
    private Config $transactionConfig;
    private Config $configFile;

    public function onEnable(): void {
        \NetherByte\NetherEconomy\libs\muqsit\invmenu\InvMenuHandler::register($this);
        if (\NetherByte\NetherEconomy\libs\muqsit\invmenu\InvMenuHandler::isRegistered()) {
            $this->getLogger()->info("InvMenuHandler is registered!");
        } else {
            $this->getLogger()->error("InvMenuHandler is NOT registered!");
        }
        $this->saveResource("config.yml");
        $this->configFile = new Config($this->getDataFolder() . "config.yml", Config::YAML, [
            "FastSwitching" => true,
            "DeathPenalty" => false,
            "PenaltyPercent" => 50,
            "JoinBonus" => false,
            "JoinBonusAmount" => 10000
        ]);
        $this->balanceConfig = new Config($this->getDataFolder() . "balances.yml", Config::YAML);
        $this->transactionConfig = new Config($this->getDataFolder() . "transactions.yml", Config::YAML);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("NetherEconomy enabled!");
    }

    public function getPurse(string $player): int {
        $data = $this->balanceConfig->get(strtolower($player), []);
        return (int)($data["purse"] ?? 0);
    }

    public function setPurse(string $player, int $amount): void {
        $data = $this->balanceConfig->get(strtolower($player), []);
        $data["purse"] = $amount;
        $this->balanceConfig->set(strtolower($player), $data);
        $this->balanceConfig->save();
        // No transaction logging here (purse-only changes are not logged)
    }

    public function getBank(string $player): int {
        $data = $this->balanceConfig->get(strtolower($player), []);
        return (int)($data["bank"] ?? 0);
    }

    public function setBank(string $player, int $amount): void {
        $data = $this->balanceConfig->get(strtolower($player), []);
        $old = $data["bank"] ?? 0;
        $data["bank"] = $amount;
        $this->balanceConfig->set(strtolower($player), $data);
        $this->balanceConfig->save();
        // Only log bank transactions
        if ($amount > $old) {
            $this->logTransaction($player, [
                'type' => 'deposit',
                'amount' => $amount - $old,
                'time' => time(),
                'by' => 'You',
            ]);
        } elseif ($amount < $old) {
            $this->logTransaction($player, [
                'type' => 'withdraw',
                'amount' => $old - $amount,
                'time' => time(),
                'by' => 'You',
            ]);
        }
    }

    public function addPurse(string $player, int $amount): void {
        $this->setPurse($player, $this->getPurse($player) + $amount);
    }

    public function addBank(string $player, int $amount): void {
        $this->setBank($player, $this->getBank($player) + $amount);
    }

    /**
     * Log a transaction for a player
     * @param string $player
     * @param array $data (should include: type, amount, time, by, [target])
     */
    public function logTransaction(string $player, array $data): void {
        $player = strtolower($player);
        $history = $this->transactionConfig->get($player, []);
        array_unshift($history, $data);
        $history = array_slice($history, 0, 10); // keep only last 10
        $this->transactionConfig->set($player, $history);
        $this->transactionConfig->save();
    }

    /**
     * Get recent transactions for a player
     * @param string $player
     * @param int $limit
     * @return array
     */
    public function getTransactions(string $player, int $limit = 10): array {
        $player = strtolower($player);
        $history = $this->transactionConfig->get($player, []);
        return array_slice($history, 0, $limit);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "bank") {
            if ($sender instanceof Player) {
                BankGUI::open($sender, $this);
                $sender->sendMessage("§a[Bank] GUI opened!");
            } else {
                $sender->sendMessage("§cThis command can only be used in-game.");
            }
            return true;
        }
        if ($command->getName() === "givecoins") {
            if (!$sender->hasPermission("nethereconomy.command.givecoins")) {
                $sender->sendMessage("§cYou do not have permission to use this command.");
                return true;
            }
            if (count($args) < 2) {
                $sender->sendMessage("§cUsage: /givecoins <player> <amount>");
                return true;
            }
            $amount = (int)array_pop($args);
            $playerName = implode(" ", $args);
            $target = $this->getServer()->getPlayerExact($playerName);
            if (!$target) {
                $sender->sendMessage("§cPlayer not found.");
                return true;
            }
            if ($amount <= 0) {
                $sender->sendMessage("§cAmount must be positive.");
                return true;
            }
            $this->addPurse($target->getName(), $amount);
            // No transaction logging for /givecoins (purse-only)
            $sender->sendMessage("§aGave $amount coin(s) to {$target->getName()}.");
            $target->sendMessage("§aYou received $amount coin(s)!");
            return true;
        }
        return false;
    }

    public function onDisable(): void {
        $this->getLogger()->info("NetherEconomy disabled!");
    }

    public function isFastSwitching(): bool {
        return $this->configFile->get("FastSwitching", true);
    }

    public function isDeathPenaltyEnabled(): bool {
        return $this->configFile->get("DeathPenalty", false);
    }
    public function getPenaltyPercent(): int {
        return (int)$this->configFile->get("PenaltyPercent", 50);
    }

    public function isJoinBonusEnabled(): bool {
        return $this->configFile->get("JoinBonus", false);
    }
    public function getJoinBonusAmount(): int {
        return (int)$this->configFile->get("JoinBonusAmount", 10000);
    }

    // Add a public static formatShortNumber to NetherEconomy
    public static function formatShortNumber($num): string {
        if ($num >= 1_000_000_000_000) return rtrim(rtrim(number_format($num / 1_000_000_000_000, 2), '0'), '.') . 'T';
        if ($num >= 1_000_000_000) return rtrim(rtrim(number_format($num / 1_000_000_000, 2), '0'), '.') . 'B';
        if ($num >= 1_000_000) return rtrim(rtrim(number_format($num / 1_000_000, 2), '0'), '.') . 'M';
        if ($num >= 1_000) return rtrim(rtrim(number_format($num / 1_000, 2), '0'), '.') . 'k';
        return (string)$num;
    }

    /**
     * Player death penalty: take a percentage of purse if enabled
     */
    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        if ($this->isDeathPenaltyEnabled()) {
            $purse = $this->getPurse($player->getName());
            $percent = $this->getPenaltyPercent();
            if ($purse > 0 && $percent > 0) {
                $penalty = (int)floor($purse * $percent / 100);
                if ($penalty > 0) {
                    $this->setPurse($player->getName(), $purse - $penalty);
                    $player->sendMessage("§c[Bank] You died and lost §6" . self::formatShortNumber($penalty) . "§c coins from your purse (" . $percent . "%)!");
                }
            }
        }
    }

    /**
     * Player join bonus: give bonus to new players if enabled
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        if ($this->isJoinBonusEnabled()) {
            $data = $this->balanceConfig->get(strtolower($name), []);
            if (!isset($data["purse"])) {
                $bonus = $this->getJoinBonusAmount();
                $this->setPurse($name, $bonus);
                $player->sendMessage("§a[Bank] Welcome! You received a join bonus of §6" . self::formatShortNumber($bonus) . "§a coins!");
            }
        }
    }
} 