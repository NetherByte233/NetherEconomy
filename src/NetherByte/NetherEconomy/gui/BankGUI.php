<?php
namespace NetherByte\NetherEconomy\gui;

use pocketmine\player\Player;
use NetherByte\NetherEconomy\libs\muqsit\invmenu\InvMenu;
use NetherByte\NetherEconomy\libs\muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\scheduler\ClosureTask;
use pocketmine\plugin\Plugin;
use NetherByte\NetherEconomy\NetherEconomy;
use NetherByte\NetherEconomy\libs\dktapps\pmforms\CustomForm;
use NetherByte\NetherEconomy\libs\dktapps\pmforms\element\Input;

class BankGUI {
    private InvMenu $menu;

    public function __construct() {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName("Bank");
        $this->menu->setListener(function($transaction) {
            $player = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();
            $plugin = $player->getServer()->getPluginManager()->getPlugin("NetherEconomy");
            if (!$plugin instanceof \NetherByte\NetherEconomy\NetherEconomy) return $transaction->discard();
            switch ($slot) {
                case 20: // Deposit
                    if ($plugin->isFastSwitching()) {
                        self::switchToDepositGUI($player, $plugin, $this->menu);
                    } else {
                        self::openDepositGUI($player, $plugin);
                    }
                    break;
                case 22: // Withdraw
                    if ($plugin->isFastSwitching()) {
                        self::switchToWithdrawGUI($player, $plugin, $this->menu);
                    } else {
                        self::openWithdrawGUI($player, $plugin);
                    }
                    break;
                case 24: // View Transactions (paper)
                    // No need to update lore here anymore, as it's always live
                    break;
                case 49: // Close
                    $player->removeCurrentWindow();
                    break;
                case 53: // Bank Upgrades
                    if ($plugin->isBankUpgradeEnabled()) {
                        if ($plugin->isFastSwitching()) {
                            self::switchToBankUpgradesGUI($player, $plugin, $this->menu);
                        } else {
                            self::openBankUpgradesGUI($player, $plugin);
                        }
                    }
                    break;
            }
            return $transaction->discard();
        });
    }

    public function send(Player $player, Plugin $plugin): void {
        $plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $plugin) {
            $this->menu->send($player);
            $itemParser = StringToItemParser::getInstance();
            $glass = $itemParser->parse("light_gray_stained_glass_pane");
            if ($glass) $glass->setCustomName(" ");
            for ($i = 0; $i < 54; $i++) {
                $this->menu->getInventory()->setItem($i, clone $glass);
            }
            $safe = function($name, $customName) use ($itemParser) {
                $item = $itemParser->parse($name);
                if ($item === null) {
                    $item = $itemParser->parse("stone");
                    $item->setCustomName("§c[ERROR] $customName");
                } else {
                    $item->setCustomName($customName);
                }
                return $item;
            };
            // Chest (Deposit)
            $bankBalance = $plugin instanceof \NetherByte\NetherEconomy\NetherEconomy ? $plugin->getBank($player->getName()) : 0;
            $chest = $itemParser->parse("chest");
            if ($chest) {
                $chest->setCustomName("§aDeposit Coins");
                $chest->setLore([
                    "§7Current Balance : §a" . self::formatShortNumber($bankBalance),
                    "",
                    "§7Deposit your money in bank to",
                    "§7keep them safe while you were",
                    "§7in adventure!",
                    "",
                    "§eClick to make deposit!"
                ]);
                $this->menu->getInventory()->setItem(20, $chest);
            } else {
                $this->menu->getInventory()->setItem(20, $safe("chest", "§aDeposit Coins"));
            }
            // Furnace (Withdraw)
            $furnace = $itemParser->parse("furnace");
            if ($furnace) {
                $furnace->setCustomName("§bWithdraw Coins");
                $furnace->setLore([
                    "§7Current Balance : §a" . self::formatShortNumber($bankBalance),
                    "",
                    "§7Take your coins out of the bank",
                    "§7to spend them.",
                    "",
                    "§eClick to withdraw coins!"
                ]);
                $this->menu->getInventory()->setItem(22, $furnace);
            } else {
                $this->menu->getInventory()->setItem(22, $safe("furnace", "§bWithdraw Coins"));
            }
            // --- Transaction paper with live lore ---
            $pluginInstance = $player->getServer()->getPluginManager()->getPlugin("NetherEconomy");
            $paper = $itemParser->parse("paper");
            if ($paper && $pluginInstance instanceof \NetherByte\NetherEconomy\NetherEconomy) {
                $transactions = $pluginInstance->getTransactions($player->getName(), 10);
                $now = time();
                $lore = [];
                foreach ($transactions as $tx) {
                    $sign = ($tx['type'] === 'deposit') ? '+' : '-';
                    $amount = $tx['amount'] ?? 0;
                    $ago = isset($tx['time']) ? self::formatAgo($now - $tx['time']) : '';
                    $by = ($tx['by'] ?? 'You') === $player->getName() ? 'By You' : 'By ' . ($tx['by'] ?? 'Unknown');
                    $lore[] = "§7$sign $amount, $ago, $by";
                }
                if (count($lore) === 0) $lore[] = "§7No transactions yet.";
                $paper->setLore($lore);
                $paper->setCustomName("§6Recent Transactions");
                $this->menu->getInventory()->setItem(24, $paper);
            } else {
                $this->menu->getInventory()->setItem(24, $safe("paper", "§eView Transactions"));
            }
            $this->menu->getInventory()->setItem(49, $safe("barrier", "§cClose"));
            // BankUpgrade feature
            if ($plugin instanceof \NetherByte\NetherEconomy\NetherEconomy && $plugin->isBankUpgradeEnabled()) {
                $torch = $itemParser->parse("redstone_torch");
                if ($torch) {
                    $torch->setCustomName("§cInformation");
                    // --- Dynamic Lore for Information ---
                    $name = $player->getName();
                    $tier = $plugin->getAccountTier($name);
                    $limits = $plugin->getAccountLimits();
                    $limit = $limits[$tier] ?? 0;
                    $accountNames = ["Starter Account", "Bronze Account", "Silver Account", "Gold Account", "Platinum Account", "Crystal Vault", "Dragon Treasury"];
                    $tierName = $accountNames[$tier] ?? ("Tier " . ($tier+1));
                    $penalty = $plugin->getPenaltyPercent();
                    $interestTime = $plugin->getInterestTime();
                    // Time unit logic
                    $unit = "minutes";
                    $displayTime = $interestTime;
                    if ($interestTime >= 1440 && $interestTime % 1440 == 0) {
                        $displayTime = $interestTime / 1440;
                        $unit = $displayTime == 1 ? "day" : "days";
                    } elseif ($interestTime >= 60 && $interestTime % 60 == 0) {
                        $displayTime = $interestTime / 60;
                        $unit = $displayTime == 1 ? "hour" : "hours";
                    } else {
                        $unit = $interestTime == 1 ? "minute" : "minutes";
                    }
                    // Max interest
                    $interestAccounts = $plugin->getInterestAccounts();
                    $interestKey = $plugin->getInterestTierKey($tier);
                    $maxInterest = isset($interestAccounts[$interestKey]["max_interest"]) ? $interestAccounts[$interestKey]["max_interest"] : 0;
                    // Next interest in
                    $globalLastInterest = $plugin->getLastInterestTime("");
                    $now = time();
                    $interval = $interestTime * 60;
                    $nextIn = max(0, $globalLastInterest + $interval - $now);
                    // Format nextIn as h m s
                    if ($nextIn >= 3600) {
                        $h = floor($nextIn / 3600);
                        $m = floor(($nextIn % 3600) / 60);
                        $s = $nextIn % 60;
                        $nextInStr = "{$h}h {$m}m {$s}s";
                    } elseif ($nextIn >= 60) {
                        $m = floor($nextIn / 60);
                        $s = $nextIn % 60;
                        $nextInStr = "{$m}m {$s}s";
                    } else {
                        $nextInStr = "{$nextIn}s";
                    }
                    $torch->setLore([
                        "§7Keep your money safe in your bank account you will lose {$penalty}% if you die",
                        "§7",
                        "§7Current Tier: §a{$tierName}",
                        "§7Balance limit: §6" . $plugin::formatShortNumber($limit),
                        "§7",
                        "§7Banker rewards you every §e{$displayTime} {$unit} §7with interest for the coins in your bank account",
                        "§7Max Interest: §6" . $plugin::formatShortNumber($maxInterest),
                        "§7Next Interest in: §b{$nextInStr}",
                    ]);
                    $this->menu->getInventory()->setItem(50, $torch);
                } else {
                    $this->menu->getInventory()->setItem(50, $safe("redstone_torch", "§cInformation"));
                }
                $gold = $itemParser->parse("gold_block");
                if ($gold) {
                    $gold->setCustomName("§6Bank Upgrades");
                    $this->menu->getInventory()->setItem(53, $gold);
                } else {
                    $this->menu->getInventory()->setItem(53, $safe("gold_block", "§6Bank Upgrades"));
                }
            }
        }), 10);
    }

    public static function open(Player $player, Plugin $plugin): void {
        (new self())->send($player, $plugin);
    }

    public static function openDepositGUI(Player $player, NetherEconomy $plugin): void {
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $menu->setName("Deposit to Bank");
        $itemParser = StringToItemParser::getInstance();
        $safe = function($name, $customName, $count = 1, $lore = []) use ($itemParser) {
            $item = $itemParser->parse($name);
            if ($item === null) {
                $item = $itemParser->parse("stone");
                $item->setCustomName("§c[ERROR] $customName");
            } else {
                $item->setCustomName($customName);
                $item->setCount($count);
                if (!empty($lore)) $item->setLore($lore);
            }
            return $item;
        };
        // Fill with glass
        $glass = $itemParser->parse("light_gray_stained_glass_pane");
        if ($glass) $glass->setCustomName(" ");
        for ($i = 0; $i < 54; $i++) {
            $menu->getInventory()->setItem($i, clone $glass);
        }
        $name = $player->getName();
        $purse = $plugin->getPurse($name);
        $bank = $plugin->getBank($name);
        // Set options (same slots as main bank GUI for consistency)
        $menu->getInventory()->setItem(20, $safe(
            "chest",
            "§aDeposit whole purse",
            64,
            [
                "§7Deposit whole purse",
                "§7Current balance: §a" . $plugin::formatShortNumber($bank),
                "§7Amount to deposit: §6" . $plugin::formatShortNumber($purse)
            ]
        ));
        $half = (int)floor($purse / 2);
        $menu->getInventory()->setItem(22, $safe(
            "chest",
            "§bHalf your purse",
            32,
            [
                "§7Deposit half purse",
                "§7Current balance: §a" . $plugin::formatShortNumber($bank),
                "§7Amount to deposit: §6" . $plugin::formatShortNumber($half)
            ]
        ));
        $menu->getInventory()->setItem(24, $safe("sign", "§eCustom Amount"));
        $menu->getInventory()->setItem(49, $safe("arrow", "§cBack"));
        $menu->setListener(function($transaction) use ($plugin) {
            $player = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();
            $name = $player->getName();
            switch ($slot) {
                case 20: // Deposit all
                    $purse = $plugin->getPurse($name);
                        $bank = $plugin->getBank($name);
                    $limit = $plugin->getBankLimit($name);
                    $toDeposit = min($purse, $limit - $bank);
                    if ($toDeposit > 0) {
                        $plugin->addBank($name, $toDeposit);
                        $plugin->setPurse($name, $purse - $toDeposit);
                        $newBank = $plugin->getBank($name);
                        if ($toDeposit < $purse) {
                            $player->sendMessage("§cBank Account limit excced upgrade account to add more!");
                        }
                        $player->sendMessage("§aDeposited §6" . self::formatShortNumber($toDeposit) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins §ain bank account!");
                    } else {
                        $player->sendMessage("§cNo money in purse to deposit or bank is full!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 22: // Deposit 50%
                    $purse = $plugin->getPurse($name);
                    $half = (int)floor($purse / 2);
                        $bank = $plugin->getBank($name);
                    $limit = $plugin->getBankLimit($name);
                    $toDeposit = min($half, $limit - $bank);
                    if ($toDeposit > 0) {
                        $plugin->addBank($name, $toDeposit);
                        $plugin->setPurse($name, $purse - $toDeposit);
                        $newBank = $plugin->getBank($name);
                        if ($toDeposit < $half) {
                            $player->sendMessage("§cBank Account limit excced upgrade account to add more!");
                        }
                        $player->sendMessage("§aDeposited §6" . self::formatShortNumber($toDeposit) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins §ain bank account!");
                    } else {
                        $player->sendMessage("§cNot enough money in purse to deposit 50% or bank is full!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 24: // Custom amount (sign)
                    $player->removeCurrentWindow();
                    $player->sendForm(new CustomForm(
                        "§eDeposit Custom Amount",
                        [new Input("amount", "Enter amount to deposit", "1000")],
                        function(Player $player, \NetherByte\NetherEconomy\libs\dktapps\pmforms\CustomFormResponse $response) use ($plugin): void {
                            $amount = self::parseShortNumber($response->getString("amount"));
                            $name = $player->getName();
                            $purse = $plugin->getPurse($name);
                                $bank = $plugin->getBank($name);
                            $limit = $plugin->getBankLimit($name);
                            $toDeposit = min($amount, $purse, $limit - $bank);
                            if ($toDeposit > 0) {
                                $plugin->addBank($name, $toDeposit);
                                $plugin->setPurse($name, $purse - $toDeposit);
                                $newBank = $plugin->getBank($name);
                                if ($toDeposit < $amount) {
                                    $player->sendMessage("§cBank Account limit excced upgrade account to add more!");
                                }
                                $player->sendMessage("§aDeposited §6" . self::formatShortNumber($toDeposit) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins §ain bank account!");
                            } else {
                                $player->sendMessage("§cInvalid amount, not enough money in purse, or bank is full!");
                            }
                        }
                    ));
                    break;
                case 49: // Back
                    $player->removeCurrentWindow();
                    self::open($player, $plugin);
                    break;
            }
            return $transaction->discard();
        });
        $menu->send($player);
    }

    public static function openWithdrawGUI(Player $player, NetherEconomy $plugin): void {
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $menu->setName("Withdraw from Bank");
        $itemParser = StringToItemParser::getInstance();
        $safe = function($name, $customName, $count = 1, $lore = []) use ($itemParser) {
            $item = $itemParser->parse($name);
            if ($item === null) {
                $item = $itemParser->parse("stone");
                $item->setCustomName("§c[ERROR] $customName");
            } else {
                $item->setCustomName($customName);
                $item->setCount($count);
                if (!empty($lore)) $item->setLore($lore);
            }
            return $item;
        };
        // Fill with glass
        $glass = $itemParser->parse("light_gray_stained_glass_pane");
        if ($glass) $glass->setCustomName(" ");
        for ($i = 0; $i < 54; $i++) {
            $menu->getInventory()->setItem($i, clone $glass);
        }
        $name = $player->getName();
        $bank = $plugin->getBank($name);
        $menu->getInventory()->setItem(19, $safe(
            "furnace",
            "§aEverything in account",
            64,
            [
                "§7Everything in account",
                "§7Current balance: §a" . $plugin::formatShortNumber($bank),
                "§7Amount to withdraw: §6" . $plugin::formatShortNumber($bank)
            ]
        ));
        $half = (int)floor($bank / 2);
        $menu->getInventory()->setItem(21, $safe(
            "furnace",
            "§bHalf your account",
            32,
            [
                "§7Withdraw half bank",
                "§7Current balance: §a" . $plugin::formatShortNumber($bank),
                "§7Amount to withdraw: §6" . $plugin::formatShortNumber($half)
            ]
        ));
        $portion = (int)floor($bank * 0.2);
        $menu->getInventory()->setItem(23, $safe(
            "furnace",
            "§eWithdraw 20%",
            20,
            [
                "§7Withdraw 20% bank",
                "§7Current balance: §a" . $plugin::formatShortNumber($bank),
                "§7Amount to withdraw: §6" . $plugin::formatShortNumber($portion)
            ]
        ));
        $menu->getInventory()->setItem(25, $safe("sign", "§eCustom Amount"));
        $menu->getInventory()->setItem(49, $safe("arrow", "§cBack"));
        $menu->setListener(function($transaction) use ($plugin) {
            $player = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();
            $name = $player->getName();
            switch ($slot) {
                case 19: // Withdraw all
                    $bank = $plugin->getBank($name);
                    if ($bank > 0) {
                        $plugin->addPurse($name, $bank);
                        $plugin->setBank($name, 0);
                        $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($bank) . " coins§a! Theres now §60 coins§a in bank account!");
                    } else {
                        $player->sendMessage("§cNo coins in bank to withdraw!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 21: // Withdraw 50%
                    $bank = $plugin->getBank($name);
                    $half = (int)floor($bank / 2);
                    if ($half > 0) {
                        $plugin->addPurse($name, $half);
                        $plugin->setBank($name, $bank - $half);
                        $newBank = $plugin->getBank($name);
                        $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($half) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins §ain bank account!");
                    } else {
                        $player->sendMessage("§cNot enough coins in bank to withdraw 50%!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 23: // Withdraw 20%
                    $bank = $plugin->getBank($name);
                    $portion = (int)floor($bank * 0.2);
                    if ($portion > 0) {
                        $plugin->addPurse($name, $portion);
                        $plugin->setBank($name, $bank - $portion);
                        $newBank = $plugin->getBank($name);
                        $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($portion) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins §ain bank account!");
                    } else {
                        $player->sendMessage("§cNot enough coins in bank to withdraw 20%!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 25: // Custom amount (sign)
                    $player->removeCurrentWindow();
                    $player->sendForm(new CustomForm(
                        "§eWithdraw Custom Amount",
                        [new Input("amount", "Enter amount to withdraw", "1000")],
                        function(Player $player, \NetherByte\NetherEconomy\libs\dktapps\pmforms\CustomFormResponse $response) use ($plugin): void {
                            $amount = self::parseShortNumber($response->getString("amount"));
                            $name = $player->getName();
                            $bank = $plugin->getBank($name);
                            if ($amount > 0 && $amount <= $bank) {
                                $plugin->addPurse($name, $amount);
                                $plugin->setBank($name, $bank - $amount);
                                $newBank = $plugin->getBank($name);
                                $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($amount) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins §ain bank account!");
                            } else {
                                $player->sendMessage("§cInvalid amount or not enough coins in bank!");
                            }
                        }
                    ));
                    break;
                case 49: // Back
                    $player->removeCurrentWindow();
                    self::open($player, $plugin);
                    break;
            }
            return $transaction->discard();
        });
        $menu->send($player);
    }

    public static function openBankUpgradesGUI(Player $player, NetherEconomy $plugin): void {
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $menu->setName("Bank Account Upgrades");
        self::fillBankUpgradesMenu($menu, $plugin, $player);
        $menu->setListener(function($transaction) use ($plugin, $player) {
            $playerObj = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();
            $name = $playerObj->getName();
            $tier = $plugin->getAccountTier($name);
            $purse = $plugin->getPurse($name);
            $costs = $plugin->getUpgradeAmounts();
            // Fix: Only check upgradeable slot if within bounds
            $upgradeableSlot = $tier + 1;
            $upgradeableSlots = [19,20,21,22,23,24,25];
            $canUpgrade = false;
            if ($upgradeableSlot >= 0 && $upgradeableSlot < count($upgradeableSlots)) {
                $canUpgrade = $slot === $upgradeableSlots[$upgradeableSlot] && $tier === $upgradeableSlot - 1 && ($purse >= ($costs[$tier] ?? PHP_INT_MAX));
            }
            if ($canUpgrade) {
                if ($plugin->isFastSwitching()) {
                    self::switchToUpgradeConfirmGUI($playerObj, $plugin, $menu, $upgradeableSlot);
                } else {
                    self::openUpgradeConfirmGUI($playerObj, $plugin, $upgradeableSlot);
                }
                return $transaction->discard();
            }
            if ($slot === 49) {
                $playerObj->removeCurrentWindow();
                self::open($playerObj, $plugin);
            }
            return $transaction->discard();
        });
        $menu->send($player);
    }

    public static function switchToBankUpgradesGUI(Player $player, NetherEconomy $plugin, InvMenu $menu): void {
        $menu->setName("Bank Account Upgrades");
        self::fillBankUpgradesMenu($menu, $plugin, $player);
        $menu->setListener(function($transaction) use ($plugin, $menu, $player) {
            $playerObj = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();
            $name = $playerObj->getName();
            $tier = $plugin->getAccountTier($name);
            $purse = $plugin->getPurse($name);
            $costs = $plugin->getUpgradeAmounts();
            $upgradeableSlot = $tier + 1;
            $upgradeableSlots = [19,20,21,22,23,24,25];
            $canUpgrade = false;
            if ($upgradeableSlot >= 0 && $upgradeableSlot < count($upgradeableSlots)) {
                $canUpgrade = $slot === $upgradeableSlots[$upgradeableSlot] && $tier === $upgradeableSlot - 1 && ($purse >= ($costs[$tier] ?? PHP_INT_MAX));
            }
            if ($canUpgrade) {
                self::switchToUpgradeConfirmGUI($playerObj, $plugin, $menu, $upgradeableSlot);
                return $transaction->discard();
            }
            if ($slot === 49) {
                self::switchToMainGUI($playerObj, $plugin, $menu);
            }
            return $transaction->discard();
        });
    }

    private static function fillBankUpgradesMenu(InvMenu $menu, NetherEconomy $plugin, Player $player): void {
        $itemParser = \pocketmine\item\StringToItemParser::getInstance();
        $glass = $itemParser->parse("light_gray_stained_glass_pane");
        if ($glass) $glass->setCustomName(" ");
        for ($i = 0; $i < 54; $i++) {
            $menu->getInventory()->setItem($i, clone $glass);
        }
        $safe = function($name, $customName, $lore = []) use ($itemParser) {
            $item = $itemParser->parse($name);
            if ($item === null) {
                $item = $itemParser->parse("stone");
                $item->setCustomName("§c[ERROR] $customName");
            } else {
                $item->setCustomName($customName);
            }
            if (!empty($lore)) $item->setLore($lore);
            return $item;
        };
        $accountNames = [
            ["Starter Account", "§a", "§7Complimentary", "§f", "§a", "§a"], // green
            ["Bronze Account", "§6", "§7Bank Upgrade", "§f", "§a", "§e"], // gold
            ["Silver Account", "§d", "§7Bank Upgrade", "§f", "§a", "§e"], // light pink
            ["Gold Account", "§5", "§7Bank Upgrade", "§f", "§a", "§e"], // dark pink
            ["Platinum Account", "§6", "§7Bank Upgrade", "§f", "§a", "§e"], // orange (using gold)
            ["Crystal Vault", "§9", "§7Bank Upgrade", "§f", "§a", "§e"], // blue
            ["Dragon Treasury", "§c", "§7Bank Upgrade", "§f", "§a", "§e"] // hot red
        ];
        $slots = [19, 20, 21, 22, 23, 24, 25];
        $items = ["brick", "gold_nugget", "raw_gold", "gold_ingot", "diamond", "gold_block", "diamond_block"];
        $limits = $plugin->getAccountLimits();
        $costs = $plugin->getUpgradeAmounts();
        $itemReqs = $plugin->getUpgradeItems();
        $tier = $plugin->getAccountTier($player->getName());
        $purse = $plugin->getPurse($player->getName());
        for ($i = 0; $i < count($slots); $i++) {
            $name = $accountNames[$i][0];
            $color = $accountNames[$i][1];
            $subtitle = $accountNames[$i][2];
            $maxColor = $accountNames[$i][3];
            $costColor = $accountNames[$i][4];
            $clickColor = $accountNames[$i][5];
            $lore = [];
            $lore[] = $subtitle;
            $lore[] = $color . ">-------Interests-------<";
            // Interest breakdown
            $interestAccounts = $plugin->getInterestAccounts();
            $interestKeys = array_keys($interestAccounts);
            $interestKey = $interestKeys[$i] ?? null;
            if ($interestKey && isset($interestAccounts[$interestKey]["brackets"])) {
                $brackets = $interestAccounts[$interestKey]["brackets"];
                $lastMax = 0;
                foreach ($brackets as $bracket) {
                    $bracketMax = $bracket["max"] ?? 0;
                    $rate = $bracket["rate"] ?? 0;
                    if ($lastMax === 0) {
                        $lore[] = "§7First " . $plugin::formatShortNumber($bracketMax) . " coins yield " . ($rate * 100) . "% interest";
                    } else {
                        $lore[] = "§7From " . $plugin::formatShortNumber($lastMax) . " to " . $plugin::formatShortNumber($bracketMax) . " yields " . ($rate * 100) . "% interest";
                    }
                    $lastMax = $bracketMax;
                }
            }
            $maxInterest = isset($interestAccounts[$interestKey]["max_interest"]) ? $interestAccounts[$interestKey]["max_interest"] : 0;
            $lore[] = "";
            $lore[] = "§7Max Interest: §6" . $plugin::formatShortNumber($maxInterest);
            $lore[] = $color . ">------------------------<";
            $lore[] = $maxColor . "Max Balance: §6" . $plugin::formatShortNumber($limits[$i] ?? 0) . " Coins";
            $cost = $costs[$i-1] ?? 0;
            $itemReq = $itemReqs[$i-1] ?? null;
            $itemReqStr = "";
            if ($i > 0 && $itemReq) {
                // Support multiple items
                if (isset($itemReq["id"]) && isset($itemReq["count"])) $itemReq = [$itemReq]; // Backward compatibility
                foreach ($itemReq as $req) {
                    if (isset($req["id"]) && isset($req["count"])) {
                        $itemReqStr .= "\n        §b" . self::getItemName($req["id"]) . " §7x " . $req["count"];
                    }
                }
            }
            if ($i === 0) {
                $lore[] = "§aCost : Free";
                if ($tier === 0) {
                    $lore[] = "§aThis is your account!";
                } elseif ($tier > 0) {
                    $lore[] = "§cYou have a better account!";
                }
            } else {
                $lore[] = $costColor . "Cost: §6" . $plugin::formatShortNumber($cost) . " Coins" . $itemReqStr;
                if ($tier === $i) {
                    $lore[] = "§aThis is your account!";
                } elseif ($tier > $i) {
                    $lore[] = "§cYou have a better account!";
                } elseif ($tier === $i-1) {
                    $hasCoins = $purse >= $cost;
                    $hasItems = true;
                    $missingItems = [];
                    if ($itemReqStr !== "") {
                        // Check for multiple items
                        $itemReqArr = $itemReqs[$i-1] ?? [];
                        if (isset($itemReqArr["id"]) && isset($itemReqArr["count"])) $itemReqArr = [$itemReqArr]; // Backward compatibility
                        foreach ($itemReqArr as $req) {
                            if (isset($req["id"]) && isset($req["count"])) {
                                if (!$plugin->hasUpgradeItem($player, $i-1)) {
                                    $missingItems[] = self::getItemName($req["id"]);
                                }
                            }
                        }
                    }
                    if ($hasCoins && $hasItems && empty($missingItems)) {
                        $lore[] = "§eClick to upgrade!";
                    } elseif (!$hasCoins && !empty($missingItems)) {
                        $lore[] = "§cNot enough coins and " . implode(" and ", $missingItems) . "!";
                    } elseif (!$hasCoins) {
                        $lore[] = "§cNot enough coins!";
                    } elseif (!empty($missingItems)) {
                        $lore[] = "§cNot enough " . implode(" and ", $missingItems) . "!";
                    }
                } else {
                    $lore[] = "§cNeed previous upgrade!";
                }
            }
            $menu->getInventory()->setItem($slots[$i], $safe($items[$i], $color . $name, $lore));
        }
        $menu->getInventory()->setItem(49, $safe("arrow", "§cBack"));
    }

    // Add a helper to format time ago
    private static function formatAgo($seconds): string {
        if ($seconds < 60) return $seconds . ' sec ago';
        if ($seconds < 3600) return intval($seconds / 60) . ' min ago';
        if ($seconds < 86400) return intval($seconds / 3600) . ' hr ago';
        return intval($seconds / 86400) . ' d ago';
    }

    // Helper to format numbers as 10k, 1M, etc.
    private static function formatShortNumber($num): string {
        if ($num >= 1_000_000_000_000) return rtrim(rtrim(number_format($num / 1_000_000_000_000, 2), '0'), '.') . 'T';
        if ($num >= 1_000_000_000) return rtrim(rtrim(number_format($num / 1_000_000_000, 2), '0'), '.') . 'B';
        if ($num >= 1_000_000) return rtrim(rtrim(number_format($num / 1_000_000, 2), '0'), '.') . 'M';
        if ($num >= 1_000) return rtrim(rtrim(number_format($num / 1_000, 2), '0'), '.') . 'k';
        return (string)$num;
    }

    // Helper to parse short numbers like 10k, 1M, 1b, etc.
    private static function parseShortNumber(string $str): int {
        $str = trim(strtolower(str_replace([',', ' '], '', $str)));
        if (preg_match('/^([0-9]*\.?[0-9]+)([kmgtb]?)$/i', $str, $matches)) {
            $num = (float)$matches[1];
            $suffix = strtolower($matches[2] ?? '');
            switch ($suffix) {
                case 'k': return (int)($num * 1_000);
                case 'm': return (int)($num * 1_000_000);
                case 'b': return (int)($num * 1_000_000_000);
                case 't': return (int)($num * 1_000_000_000_000);
                default: return (int)$num;
            }
        }
        return -1; // Invalid
    }

    // FastSwitching: update menu in-place
    public static function switchToDepositGUI(Player $player, NetherEconomy $plugin, InvMenu $menu): void {
        $itemParser = \pocketmine\item\StringToItemParser::getInstance();
        $safe = function($name, $customName, $count = 1, $lore = []) use ($itemParser) {
            $item = $itemParser->parse($name);
            if ($item === null) {
                $item = $itemParser->parse("stone");
                $item->setCustomName("§c[ERROR] $customName");
            } else {
                $item->setCustomName($customName);
                $item->setCount($count);
                if (!empty($lore)) $item->setLore($lore);
            }
            return $item;
        };
        $glass = $itemParser->parse("light_gray_stained_glass_pane");
        if ($glass) $glass->setCustomName(" ");
        for ($i = 0; $i < 54; $i++) {
            $menu->getInventory()->setItem($i, clone $glass);
        }
        $menu->setName("Deposit to Bank");
        $name = $player->getName();
        $purse = $plugin->getPurse($name);
        $bank = $plugin->getBank($name);
        $menu->getInventory()->setItem(20, $safe(
            "chest",
            "§aDeposit whole purse",
            64,
            [
                "§7Deposit whole purse",
                "§7Current balance: §a" . $plugin::formatShortNumber($bank),
                "§7Amount to deposit: §6" . $plugin::formatShortNumber($purse)
            ]
        ));
        $half = (int)floor($purse / 2);
        $menu->getInventory()->setItem(22, $safe(
            "chest",
            "§bHalf your purse",
            32,
            [
                "§7Deposit half purse",
                "§7Current balance: §a" . $plugin::formatShortNumber($bank),
                "§7Amount to deposit: §6" . $plugin::formatShortNumber($half)
            ]
        ));
        $menu->getInventory()->setItem(24, $safe("sign", "§eCustom Amount"));
        $menu->getInventory()->setItem(49, $safe("arrow", "§cBack"));
        $menu->setListener(function($transaction) use ($plugin, $menu) {
            $player = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();
            $name = $player->getName();
            switch ($slot) {
                case 20: // Deposit all
                    $purse = $plugin->getPurse($name);
                        $bank = $plugin->getBank($name);
                    $limit = $plugin->getBankLimit($name);
                    $toDeposit = min($purse, $limit - $bank);
                    if ($toDeposit > 0) {
                        $plugin->addBank($name, $toDeposit);
                        $plugin->setPurse($name, $purse - $toDeposit);
                        $newBank = $plugin->getBank($name);
                        if ($toDeposit < $purse) {
                            $player->sendMessage("§cBank Account limit excced upgrade account to add more!");
                        }
                        $player->sendMessage("§aDeposited §6" . self::formatShortNumber($toDeposit) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins§a in bank account!");
                    } else {
                        $player->sendMessage("§cNo money in purse to deposit or bank is full!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 22: // Deposit 50%
                    $purse = $plugin->getPurse($name);
                    $half = (int)floor($purse / 2);
                        $bank = $plugin->getBank($name);
                    $limit = $plugin->getBankLimit($name);
                    $toDeposit = min($half, $limit - $bank);
                    if ($toDeposit > 0) {
                        $plugin->addBank($name, $toDeposit);
                        $plugin->setPurse($name, $purse - $toDeposit);
                        $newBank = $plugin->getBank($name);
                        if ($toDeposit < $half) {
                            $player->sendMessage("§cBank Account limit excced upgrade account to add more!");
                        }
                        $player->sendMessage("§aDeposited §6" . self::formatShortNumber($toDeposit) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins§a in bank account!");
                    } else {
                        $player->sendMessage("§cNot enough money in purse to deposit 50% or bank is full!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 24: // Custom amount (sign)
                    $player->removeCurrentWindow();
                    $player->sendForm(new CustomForm(
                        "§eDeposit Custom Amount",
                        [new Input("amount", "Enter amount to deposit", "1000")],
                        function(Player $player, \NetherByte\NetherEconomy\libs\dktapps\pmforms\CustomFormResponse $response) use ($plugin): void {
                            $amount = self::parseShortNumber($response->getString("amount"));
                            $name = $player->getName();
                            $purse = $plugin->getPurse($name);
                                $bank = $plugin->getBank($name);
                            $limit = $plugin->getBankLimit($name);
                            $toDeposit = min($amount, $purse, $limit - $bank);
                            if ($toDeposit > 0) {
                                $plugin->addBank($name, $toDeposit);
                                $plugin->setPurse($name, $purse - $toDeposit);
                                $newBank = $plugin->getBank($name);
                                if ($toDeposit < $amount) {
                                    $player->sendMessage("§cBank Account limit excced upgrade account to add more!");
                                }
                                $player->sendMessage("§aDeposited §6" . self::formatShortNumber($toDeposit) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins §ain bank account!");
                            } else {
                                $player->sendMessage("§cInvalid amount, not enough money in purse, or bank is full!");
                            }
                        }
                    ));
                    break;
                case 49: // Back
                    // Go back to main menu
                    BankGUI::switchToMainGUI($player, $plugin, $menu);
                    break;
            }
            return $transaction->discard();
        });
    }

    public static function switchToWithdrawGUI(Player $player, NetherEconomy $plugin, InvMenu $menu): void {
        $itemParser = \pocketmine\item\StringToItemParser::getInstance();
        $safe = function($name, $customName, $count = 1, $lore = []) use ($itemParser) {
            $item = $itemParser->parse($name);
            if ($item === null) {
                $item = $itemParser->parse("stone");
                $item->setCustomName("§c[ERROR] $customName");
            } else {
                $item->setCustomName($customName);
                $item->setCount($count);
                if (!empty($lore)) $item->setLore($lore);
            }
            return $item;
        };
        $glass = $itemParser->parse("light_gray_stained_glass_pane");
        if ($glass) $glass->setCustomName(" ");
        for ($i = 0; $i < 54; $i++) {
            $menu->getInventory()->setItem($i, clone $glass);
        }
        $menu->setName("Withdraw from Bank");
        $name = $player->getName();
        $bank = $plugin->getBank($name);
        $menu->getInventory()->setItem(19, $safe(
            "furnace",
            "§aEverything in account",
            64,
            [
                "§7Everything in account",
                "§7Current balance: §a" . $plugin::formatShortNumber($bank),
                "§7Amount to withdraw: §6" . $plugin::formatShortNumber($bank)
            ]
        ));
        $half = (int)floor($bank / 2);
        $menu->getInventory()->setItem(21, $safe(
            "furnace",
            "§bHalf your account",
            32,
            [
                "§7Withdraw half bank",
                "§7Current balance: §a" . $plugin::formatShortNumber($bank),
                "§7Amount to withdraw: §6" . $plugin::formatShortNumber($half)
            ]
        ));
        $portion = (int)floor($bank * 0.2);
        $menu->getInventory()->setItem(23, $safe(
            "furnace",
            "§eWithdraw 20%",
            20,
            [
                "§7Withdraw 20% bank",
                "§7Current balance: §a" . $plugin::formatShortNumber($bank),
                "§7Amount to withdraw: §6" . $plugin::formatShortNumber($portion)
            ]
        ));
        $menu->getInventory()->setItem(25, $safe("sign", "§eCustom Amount"));
        $menu->getInventory()->setItem(49, $safe("arrow", "§cBack"));
        $menu->setListener(function($transaction) use ($plugin, $menu) {
            $player = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();
            $name = $player->getName();
            switch ($slot) {
                case 19: // Withdraw all
                    $bank = $plugin->getBank($name);
                    if ($bank > 0) {
                        $plugin->addPurse($name, $bank);
                        $plugin->setBank($name, 0);
                        $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($bank) . " coins§a! Theres now §60 coins§a in bank account!");
                    } else {
                        $player->sendMessage("§cNo coins in bank to withdraw!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 21: // Withdraw 50%
                    $bank = $plugin->getBank($name);
                    $half = (int)floor($bank / 2);
                    if ($half > 0) {
                        $plugin->addPurse($name, $half);
                        $plugin->setBank($name, $bank - $half);
                        $newBank = $plugin->getBank($name);
                        $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($half) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins §ain bank account!");
                    } else {
                        $player->sendMessage("§cNot enough coins in bank to withdraw 50%!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 23: // Withdraw 20%
                    $bank = $plugin->getBank($name);
                    $portion = (int)floor($bank * 0.2);
                    if ($portion > 0) {
                        $plugin->addPurse($name, $portion);
                        $plugin->setBank($name, $bank - $portion);
                        $newBank = $plugin->getBank($name);
                        $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($portion) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins §ain bank account!");
                    } else {
                        $player->sendMessage("§cNot enough coins in bank to withdraw 20%!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 25: // Custom amount (sign)
                    $player->removeCurrentWindow();
                    $player->sendForm(new CustomForm(
                        "§eWithdraw Custom Amount",
                        [new Input("amount", "Enter amount to withdraw", "1000")],
                        function(Player $player, \NetherByte\NetherEconomy\libs\dktapps\pmforms\CustomFormResponse $response) use ($plugin): void {
                            $amount = self::parseShortNumber($response->getString("amount"));
                            $name = $player->getName();
                            $bank = $plugin->getBank($name);
                            if ($amount > 0 && $amount <= $bank) {
                                $plugin->addPurse($name, $amount);
                                $plugin->setBank($name, $bank - $amount);
                                $newBank = $plugin->getBank($name);
                                $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($amount) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins §ain bank account!");
                            } else {
                                $player->sendMessage("§cInvalid amount or not enough coins in bank!");
                            }
                        }
                    ));
                    break;
                case 49: // Back
                    // Go back to main menu
                    BankGUI::switchToMainGUI($player, $plugin, $menu);
                    break;
            }
            return $transaction->discard();
        });
    }

    public static function switchToMainGUI(Player $player, NetherEconomy $plugin, InvMenu $menu): void {
        // Rebuild the main menu in-place
        $itemParser = \pocketmine\item\StringToItemParser::getInstance();
        $glass = $itemParser->parse("light_gray_stained_glass_pane");
        if ($glass) $glass->setCustomName(" ");
        for ($i = 0; $i < 54; $i++) {
            $menu->getInventory()->setItem($i, clone $glass);
        }
        $menu->setName("Bank");
        $safe = function($name, $customName) use ($itemParser) {
            $item = $itemParser->parse($name);
            if ($item === null) {
                $item = $itemParser->parse("stone");
                $item->setCustomName("§c[ERROR] $customName");
            } else {
                $item->setCustomName($customName);
            }
            return $item;
        };
        // Chest (Deposit)
        $bankBalance = $plugin->getBank($player->getName());
        $chest = $itemParser->parse("chest");
        if ($chest) {
            $chest->setCustomName("§aDeposit Coins");
            $chest->setLore([
                "§7Current Balance : §a" . self::formatShortNumber($bankBalance),
                "",
                "§7Deposit your money in bank to",
                "§7keep them safe while you were",
                "§7in adventure!",
                "",
                "§eClick to make deposit!"
            ]);
            $menu->getInventory()->setItem(20, $chest);
        } else {
            $menu->getInventory()->setItem(20, $safe("chest", "§aDeposit Coins"));
        }
        // Furnace (Withdraw)
        $furnace = $itemParser->parse("furnace");
        if ($furnace) {
            $furnace->setCustomName("§bWithdraw Coins");
            $furnace->setLore([
                "§7Current Balance : §a" . self::formatShortNumber($bankBalance),
                "",
                "§7Take your coins out of the bank",
                "§7to spend them.",
                "",
                "§eClick to withdraw coins!"
            ]);
            $menu->getInventory()->setItem(22, $furnace);
        } else {
            $menu->getInventory()->setItem(22, $safe("furnace", "§bWithdraw Coins"));
        }
        // --- Transaction paper with live lore ---
        $paper = $itemParser->parse("paper");
        if ($paper && $plugin instanceof \NetherByte\NetherEconomy\NetherEconomy) {
            $transactions = $plugin->getTransactions($player->getName(), 10);
            $now = time();
            $lore = ["§6Recent Transactions"];
            foreach ($transactions as $tx) {
                $sign = ($tx['type'] === 'deposit') ? '+' : '-';
                $amount = $tx['amount'] ?? 0;
                $ago = isset($tx['time']) ? self::formatAgo($now - $tx['time']) : '';
                $by = ($tx['by'] ?? 'You') === $player->getName() ? 'By You' : 'By ' . ($tx['by'] ?? 'Unknown');
                $lore[] = "§7$sign $amount, $ago, $by";
            }
            if (count($lore) === 1) $lore[] = "§7No transactions yet.";
            $paper->setLore($lore);
            $paper->setCustomName("§6Recent Transactions");
            $menu->getInventory()->setItem(24, $paper);
        } else {
            $menu->getInventory()->setItem(24, $safe("paper", "§eView Transactions"));
        }
        $menu->getInventory()->setItem(49, $safe("barrier", "§cClose"));
        // BankUpgrade feature
        if ($plugin instanceof \NetherByte\NetherEconomy\NetherEconomy && $plugin->isBankUpgradeEnabled()) {
            $torch = $itemParser->parse("redstone_torch");
            if ($torch) {
                $torch->setCustomName("§cInformation");
                $menu->getInventory()->setItem(50, $torch);
            } else {
                $menu->getInventory()->setItem(50, $safe("redstone_torch", "§cInformation"));
            }
            $gold = $itemParser->parse("gold_block");
            if ($gold) {
                $gold->setCustomName("§6Bank Upgrades");
                $menu->getInventory()->setItem(53, $gold);
            } else {
                $menu->getInventory()->setItem(53, $safe("gold_block", "§6Bank Upgrades"));
            }
        }
        // Restore main menu listener
        $menu->setListener(function($transaction) use ($plugin, $menu) {
            $player = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();
            switch ($slot) {
                case 20: // Deposit
                    if ($plugin->isFastSwitching()) {
                        BankGUI::switchToDepositGUI($player, $plugin, $menu);
                    } else {
                        BankGUI::openDepositGUI($player, $plugin);
                    }
                    break;
                case 22: // Withdraw
                    if ($plugin->isFastSwitching()) {
                        BankGUI::switchToWithdrawGUI($player, $plugin, $menu);
                    } else {
                        BankGUI::openWithdrawGUI($player, $plugin);
                    }
                    break;
                case 24: // View Transactions (paper)
                    // No need to update lore here anymore, as it's always live
                    break;
                case 49: // Close
                    $player->removeCurrentWindow();
                    break;
                case 53: // Bank Upgrades
                    if ($plugin->isBankUpgradeEnabled()) {
                        if ($plugin->isFastSwitching()) {
                            BankGUI::switchToBankUpgradesGUI($player, $plugin, $menu);
                        } else {
                            BankGUI::openBankUpgradesGUI($player, $plugin);
                        }
                    }
                    break;
            }
            return $transaction->discard();
        });
    }

    // Confirmation GUI for FastSwitching (in-place)
    public static function switchToUpgradeConfirmGUI(Player $player, NetherEconomy $plugin, InvMenu $menu, int $upgradeTier): void {
        $itemParser = \pocketmine\item\StringToItemParser::getInstance();
        $glass = $itemParser->parse("light_gray_stained_glass_pane");
        if ($glass) $glass->setCustomName(" ");
        for ($i = 0; $i < 54; $i++) {
            $menu->getInventory()->setItem($i, clone $glass);
        }
        $red = $itemParser->parse("red_concrete");
        if ($red) $red->setCustomName("§cCancel");
        $green = $itemParser->parse("green_concrete");
        if ($green) {
            $green->setCustomName("§aConfirm");
            $cost = $plugin->getUpgradeAmounts()[$upgradeTier-1] ?? 0;
            $itemReq = $plugin->getUpgradeItems()[$upgradeTier-1] ?? null;
            $accountNames = ["Starter Account", "Bronze Account", "Silver Account", "Gold Account", "Platinum Account", "Crystal Vault", "Dragon Treasury"];
            $lore = [
                "§aConfirm",
                "§fUpgrading bank: §e" . ($accountNames[$upgradeTier] ?? "") . "",
                "§fCost: §6" . NetherEconomy::formatShortNumber($cost) . " Coins"
            ];
            if ($itemReq && isset($itemReq["id"]) && isset($itemReq["count"])) {
                $lore[] = "§b" . self::getItemName($itemReq["id"]) . " §7x " . $itemReq["count"];
            }
            $lore[] = "§eClick to upgrade!";
            $green->setLore($lore);
        }
        $menu->getInventory()->setItem(20, $red);
        $menu->getInventory()->setItem(24, $green);
        $menu->setListener(function($transaction) use ($plugin, $menu, $upgradeTier, $player) {
            $slot = $transaction->getAction()->getSlot();
            if ($slot === 20) {
                self::switchToBankUpgradesGUI($player, $plugin, $menu);
            } elseif ($slot === 24) {
                $name = $player->getName();
                if ($plugin->tryUpgradeAccount($name)) {
                    $player->sendMessage("§aBank account upgraded!");
                } else {
                    $player->sendMessage("§cUpgrade failed! Not enough coins or required items.");
                }
                self::switchToBankUpgradesGUI($player, $plugin, $menu);
            }
            return $transaction->discard();
        });
    }

    // Confirmation GUI for non-FastSwitching (new menu)
    public static function openUpgradeConfirmGUI(Player $player, NetherEconomy $plugin, int $upgradeTier): void {
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
        $menu->setName("Confirm Upgrade");
        $itemParser = \pocketmine\item\StringToItemParser::getInstance();
        $glass = $itemParser->parse("light_gray_stained_glass_pane");
        if ($glass) $glass->setCustomName(" ");
        for ($i = 0; $i < 27; $i++) {
            $menu->getInventory()->setItem($i, clone $glass);
        }
        $red = $itemParser->parse("red_concrete");
        if ($red) $red->setCustomName("§cCancel");
        $green = $itemParser->parse("green_concrete");
        if ($green) {
            $green->setCustomName("§aConfirm");
            $cost = $plugin->getUpgradeAmounts()[$upgradeTier-1] ?? 0;
            $itemReq = $plugin->getUpgradeItems()[$upgradeTier-1] ?? null;
            $accountNames = ["Starter Account", "Bronze Account", "Silver Account", "Gold Account", "Platinum Account", "Crystal Vault", "Dragon Treasury"];
            $lore = [
                "§aConfirm",
                "§fUpgrading bank: §e" . ($accountNames[$upgradeTier] ?? "") . "",
                "§fCost: §6" . NetherEconomy::formatShortNumber($cost) . " Coins"
            ];
            if ($itemReq && isset($itemReq["id"]) && isset($itemReq["count"])) {
                $lore[] = "§b" . self::getItemName($itemReq["id"]) . " §7x " . $itemReq["count"];
            }
            $lore[] = "§eClick to upgrade!";
            $green->setLore($lore);
        }
        $menu->getInventory()->setItem(11, $red);
        $menu->getInventory()->setItem(15, $green);
        $menu->setListener(function($transaction) use ($plugin, $player, $upgradeTier) {
            $slot = $transaction->getAction()->getSlot();
            if ($slot === 11) {
                $player->removeCurrentWindow();
                self::openBankUpgradesGUI($player, $plugin);
            } elseif ($slot === 15) {
                $name = $player->getName();
                if ($plugin->tryUpgradeAccount($name)) {
                    $player->sendMessage("§aBank account upgraded!");
                } else {
                    $player->sendMessage("§cUpgrade failed! Not enough coins or required items.");
                }
                $player->removeCurrentWindow();
                self::openBankUpgradesGUI($player, $plugin);
            }
            return $transaction->discard();
        });
        $menu->send($player);
    }

    // Helper to get a readable item name from an item id
    private static function getItemName(string $id): string {
        $parts = explode(":", $id);
        return ucfirst(str_replace("_", " ", $parts[count($parts)-1]));
    }
} 
