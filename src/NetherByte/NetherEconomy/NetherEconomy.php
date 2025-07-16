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
            "FastSwitching" => false,
            "DeathPenalty" => false,
              "PenaltyPercent" => 50,
            "JoinBonus" => false,
              "JoinBonusAmount" => 10000,
            "BankUpgrade" => false,
              "AccountLimit" => [50000000, 100000000, 250000000, 500000000, 1000000000, 6000000000, 60000000000],
              "UpgradeAmmount" => [5000000, 10000000, 25000000, 50000000, 100000000, 200000000],
              "UpgradeItems" => [null, null, ["id" => "minecraft:gold_block", "count" => 100], ["id" => "minecraft:diamond_block", "count" => 100], null, null],
            "InterestTime" => 60,
               "accounts" => [
                   "starter" => [
                      "brackets" => [
                          ["max" => 10000000, "rate" => 0.02],
                          ["max" => 15000000, "rate" => 0.01]
                      ],
                      "max_interest" => 250000
                ],
                   "bronze" => [
                      "brackets" => [
                          ["max" => 15000000, "rate" => 0.02],
                          ["max" => 20000000, "rate" => 0.01]
                      ],
                      "max_interest" => 300000
                    ],
                    "silver" => [
                      "brackets" => [
                          ["max" => 15000000, "rate" => 0.02],
                          ["max" => 20000000, "rate" => 0.01],
                          ["max" => 30000000, "rate" => 0.005]
                      ],
                      "max_interest" => 350000
                    ],
                    "gold" => [
                      "brackets" => [
                          ["max" => 15000000, "rate" => 0.02],
                          ["max" => 20000000, "rate" => 0.01],
                          ["max" => 30000000, "rate" => 0.005],
                          ["max" => 50000000, "rate" => 0.002]
                      ],
                      "max_interest" => 390000
                    ],
                    "diamond" => [
                      "brackets" => [
                          ["max" => 15000000, "rate" => 0.02],
                          ["max" => 20000000, "rate" => 0.01],
                          ["max" => 30000000, "rate" => 0.005],
                          ["max" => 50000000, "rate" => 0.002],
                          ["max" => 160000000, "rate" => 0.001]
                      ],
                      "max_interest" => 500000
                    ],
                    "crystal" => [
                      "brackets" => [
                          ["max" => 15000000, "rate" => 0.02],
                          ["max" => 20000000, "rate" => 0.01],
                          ["max" => 30000000, "rate" => 0.005],
                          ["max" => 50000000, "rate" => 0.002],
                          ["max" => 160000000, "rate" => 0.001],
                          ["max" => 5100000000, "rate" => 0.0001]
                      ],
                      "max_interest" => 1000000
                    ],
                    "dragon" => [
                      "brackets" => [
                          ["max" => 15000000, "rate" => 0.02],
                          ["max" => 20000000, "rate" => 0.01],
                          ["max" => 30000000, "rate" => 0.005],
                          ["max" => 50000000, "rate" => 0.002],
                          ["max" => 160000000, "rate" => 0.001],
                          ["max" => 5100000000, "rate" => 0.0001],
                          ["max" => 55000000000, "rate" => 0.00001]
                      ],
                      "max_interest" => 1500000
                ]
            ]
        ]);
        $this->balanceConfig = new Config($this->getDataFolder() . "balances.yml", Config::YAML);
        $this->transactionConfig = new Config($this->getDataFolder() . "transactions.yml", Config::YAML);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("NetherEconomy enabled!");
        // Schedule interest payout every InterestTime minutes
        $intervalTicks = 20 * 60 * $this->getInterestTime(); // 1 minute = 60s = 60*20 ticks
        $this->getScheduler()->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function() {
            $allPlayers = $this->balanceConfig->getAll();
            foreach ($allPlayers as $playerName => $data) {
                $playerObj = $this->getServer()->getPlayerExact($playerName);
                $this->payInterestToName($playerName, $playerObj);
            }
        }), $intervalTicks);
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

    public function setBank(string $player, int $amount, bool $logTransaction = true): void {
        $data = $this->balanceConfig->get(strtolower($player), []);
        $old = $data["bank"] ?? 0;
        $data["bank"] = $amount;
        $this->balanceConfig->set(strtolower($player), $data);
        $this->balanceConfig->save();
        // Only log bank transactions
        if ($logTransaction) {
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
    }

    public function addPurse(string $player, int $amount): void {
        $this->setPurse($player, $this->getPurse($player) + $amount);
    }

    public function addBank(string $player, int $amount, bool $logTransaction = true): void {
        $this->setBank($player, $this->getBank($player) + $amount, $logTransaction);
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
        if ($command->getName() === "payinterest") {
            if (!$sender->hasPermission("nethereconomy.command.payinterest")) {
                $sender->sendMessage("§cYou do not have permission to use this command.");
                return true;
            }
            $count = 0;
            $allPlayers = $this->balanceConfig->getAll();
            foreach ($allPlayers as $playerName => $data) {
                $playerObj = $this->getServer()->getPlayerExact($playerName);
                if ($this->payInterestToName($playerName, $playerObj)) {
                    $count++;
                }
            }
            $sender->sendMessage("§aPaid interest to $count player(s).");
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

    public function isBankUpgradeEnabled(): bool {
        return $this->configFile->get("BankUpgrade", false);
    }

    /**
     * Get the array of account limits for each tier.
     */
    public function getAccountLimits(): array {
        return $this->configFile->get("AccountLimit", [50000000, 100000000, 250000000, 500000000, 1000000000, 6000000000, 60000000000]);
    }
    /**
     * Get the array of upgrade costs for each tier (except starter).
     */
    public function getUpgradeAmounts(): array {
        return $this->configFile->get("UpgradeAmmount", [5000000, 10000000, 25000000, 50000000, 100000000, 200000000]);
    }
    /**
     * Get the array of item requirements for each upgrade (null or [id, count] per tier)
     */
    public function getUpgradeItems(): array {
        return $this->configFile->get("UpgradeItems", [null, null, ["id" => "minecraft:gold_block", "count" => 100], null, null, null, null]);
    }
    /**
     * Check if player has all required items for upgrade (returns true if no items required)
     */
    public function hasUpgradeItem(Player $player, int $tier): bool {
        $items = $this->getUpgradeItems();
        $reqs = $items[$tier] ?? null;
        if (!$reqs) return true;
        if (isset($reqs["id"]) && isset($reqs["count"])) $reqs = [$reqs]; // Backward compatibility for single item
        $inv = $player->getInventory();
        foreach ($reqs as $req) {
            if (!isset($req["id"]) || !isset($req["count"])) return false;
            $item = \pocketmine\item\StringToItemParser::getInstance()->parse($req["id"]);
            if ($item === null) return false;
            $item->setCount($req["count"]);
            if (!$inv->contains($item)) return false;
        }
        return true;
    }
    /**
     * Remove all required items from player inventory (if present)
     */
    public function removeUpgradeItem(Player $player, int $tier): void {
        $items = $this->getUpgradeItems();
        $reqs = $items[$tier] ?? null;
        if (!$reqs) return;
        if (isset($reqs["id"]) && isset($reqs["count"])) $reqs = [$reqs]; // Backward compatibility for single item
        $inv = $player->getInventory();
        foreach ($reqs as $req) {
            if (!isset($req["id"]) || !isset($req["count"])) continue;
            $item = \pocketmine\item\StringToItemParser::getInstance()->parse($req["id"]);
            if ($item === null) continue;
            $item->setCount($req["count"]);
            $inv->removeItem($item);
        }
    }
    /**
     * Get a player's current account tier (0 = starter, 1 = bronze, ...)
     */
    public function getAccountTier(string $player): int {
        $data = $this->balanceConfig->get(strtolower($player), []);
        return (int)($data["account_tier"] ?? 0);
    }
    /**
     * Set a player's account tier.
     */
    public function setAccountTier(string $player, int $tier): void {
        $data = $this->balanceConfig->get(strtolower($player), []);
        $data["account_tier"] = $tier;
        $this->balanceConfig->set(strtolower($player), $data);
        $this->balanceConfig->save();
    }
    /**
     * Get a player's max bank limit (unlimited if BankUpgrade is false)
     */
    public function getBankLimit(string $player): int {
        if (!$this->isBankUpgradeEnabled()) return PHP_INT_MAX;
        $tier = $this->getAccountTier($player);
        $limits = $this->getAccountLimits();
        return $limits[$tier] ?? end($limits);
    }
    /**
     * Try to upgrade a player's account tier. Returns true if successful, false if not enough money or items or maxed.
     */
    public function tryUpgradeAccount(string $player): bool {
        $p = $this->getServer()->getPlayerExact($player);
        $tier = $this->getAccountTier($player);
        $limits = $this->getAccountLimits();
        $upgrades = $this->getUpgradeAmounts();
        $items = $this->getUpgradeItems();
        if ($tier >= count($limits) - 1) return false; // Already maxed
        $cost = $upgrades[$tier] ?? null;
        $itemReq = $items[$tier] ?? null;
        $purse = $this->getPurse($player);
        if ($cost !== null && $purse < $cost) return false;
        if ($itemReq && $p instanceof Player) {
            if (!$this->hasUpgradeItem($p, $tier)) return false;
            $this->removeUpgradeItem($p, $tier);
        }
        $this->setPurse($player, $purse - ($cost ?? 0));
        $this->setAccountTier($player, $tier + 1);
        return true;
    }

    /**
     * Get interest payout interval in minutes
     */
    public function getInterestTime(): int {
        return (int)$this->configFile->get("InterestTime", 60);
    }
    /**
     * Get interest config for all account tiers
     */
    public function getInterestAccounts(): array {
        return $this->configFile->get("accounts", []);
    }
    /**
     * Get the interest config key for a player's tier (e.g. 'starter', 'bronze', ...)
     */
    public function getInterestTierKey(int $tier): string {
        $keys = array_keys($this->getInterestAccounts());
        return $keys[$tier] ?? "starter";
    }
    /**
     * Calculate interest for a player (returns [amount, breakdown])
     * Only applies rates up to the max of each bracket, ignores balance above last bracket.
     */
    public function calculateInterest(string $player): array {
        $tier = $this->getAccountTier($player);
        $key = $this->getInterestTierKey($tier);
        $accounts = $this->getInterestAccounts();
        $bank = $this->getBank($player);
        $interest = 0;
        $breakdown = [];
        if (!isset($accounts[$key]["brackets"])) return [0, []];
        $brackets = $accounts[$key]["brackets"];
        $max_interest = $accounts[$key]["max_interest"] ?? PHP_INT_MAX;
        $lastMax = 0;
        foreach ($brackets as $bracket) {
            $bracketMax = $bracket["max"] ?? 0;
            $rate = $bracket["rate"] ?? 0;
            if ($bank > $lastMax) {
                $amountInBracket = min($bank, $bracketMax) - $lastMax;
                if ($amountInBracket > 0) {
                    $add = $amountInBracket * $rate;
                    $interest += $add;
                    $breakdown[] = ["from" => $lastMax, "to" => $bracketMax, "rate" => $rate, "amount" => $add];
                }
            }
            $lastMax = $bracketMax;
            if ($bank <= $bracketMax) break;
        }
        $interest = min((int)round($interest), $max_interest);
        return [$interest, $breakdown];
    }
    /**
     * Get last interest payout timestamp for a player
     */
    public function getLastInterestTime(string $player): int {
        $data = $this->balanceConfig->get(strtolower($player), []);
        return (int)($data["last_interest"] ?? 0);
    }
    /**
     * Set last interest payout timestamp for a player
     */
    public function setLastInterestTime(string $player, int $time): void {
        $data = $this->balanceConfig->get(strtolower($player), []);
        $data["last_interest"] = $time;
        $this->balanceConfig->set(strtolower($player), $data);
        $this->balanceConfig->save();
    }
    /**
     * Pay interest to a player if eligible (returns true if paid)
     */
    public function payInterest(Player $player): bool {
        // DEPRECATED: Use payInterestToName instead
        return $this->payInterestToName($player->getName(), $player);
    }

    /**
     * Pay interest to a player by name (offline or online)
     * @param string $playerName
     * @param Player|null $playerObj
     * @return bool
     */
    public function payInterestToName(string $playerName, ?Player $playerObj = null): bool {
        // Interest system only works if BankUpgrade is enabled
        if (!$this->isBankUpgradeEnabled()) {
            return false;
        }
        $interval = $this->getInterestTime() * 60;
        $last = $this->getLastInterestTime($playerName);
        $now = time();
        if ($now - $last < $interval) return false;
        [$amount, $breakdown] = $this->calculateInterest($playerName);
        if ($amount > 0) {
            $currentBank = $this->getBank($playerName);
            $bankLimit = $this->getBankLimit($playerName);
            $space = $bankLimit - $currentBank;
            $toDeposit = max(0, min($amount, $space));
            if ($toDeposit > 0) {
                $this->addBank($playerName, $toDeposit, false); // Don't log 'You' transaction
                $this->setLastInterestTime($playerName, $now);
                $this->logTransaction($playerName, [
                    'type' => 'deposit',
                    'amount' => $toDeposit,
                    'time' => $now,
                    'by' => 'Bank Interest',
                ]);
            }
            if ($playerObj instanceof Player) {
                if ($toDeposit < $amount) {
                    $playerObj->sendMessage("§cYour Bank interest for this time is §6" . self::formatShortNumber($amount) . "§c but the bank limit exceeds");
                    if ($toDeposit > 0) {
                        $playerObj->sendMessage("§aDeposited §6" . self::formatShortNumber($toDeposit) . "§a in your bank account");
                    }
                } else if ($toDeposit > 0) {
                    $playerObj->sendMessage("§aYou received §6" . self::formatShortNumber($toDeposit) . "§a coins as bank interest!");
                }
            }
            return $toDeposit > 0;
        }
        return false;
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
                    $player->sendMessage("§c You died and lost §6" . self::formatShortNumber($penalty) . "§c coins from your purse (" . $percent . "%)!");
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
        $data = $this->balanceConfig->get(strtolower($name), []);
        if (!isset($data["account_tier"])) {
            $data["account_tier"] = 0;
            $this->balanceConfig->set(strtolower($name), $data);
            $this->balanceConfig->save();
        }
        // Set last_interest if not set
        if (!isset($data["last_interest"])) {
            $data["last_interest"] = time();
            $this->balanceConfig->set(strtolower($name), $data);
            $this->balanceConfig->save();
        }
        if ($this->isJoinBonusEnabled()) {
            if (!isset($data["purse"])) {
                $bonus = $this->getJoinBonusAmount();
                $this->setPurse($name, $bonus);
                $player->sendMessage("§a Welcome! You received a join bonus of §6" . self::formatShortNumber($bonus) . "§a coins!");
            }
        }
    }
} 