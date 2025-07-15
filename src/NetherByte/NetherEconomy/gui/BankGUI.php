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
                    $player->sendMessage("§c[Bank] Closed.");
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
            $plugin = $player->getServer()->getPluginManager()->getPlugin("NetherEconomy");
            $paper = $itemParser->parse("paper");
            if ($paper && $plugin instanceof \NetherByte\NetherEconomy\NetherEconomy) {
                $transactions = $plugin->getTransactions($player->getName(), 10);
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
                    if ($purse > 0) {
                        $plugin->addBank($name, $purse);
                        $plugin->setPurse($name, 0);
                        $bank = $plugin->getBank($name);
                        $player->sendMessage("§aDeposited §6" . self::formatShortNumber($purse) . " coins§a! Theres now §6" . self::formatShortNumber($bank) . " coins §ain bank account!");
                    } else {
                        $player->sendMessage("§cNo money in purse to deposit!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 22: // Deposit 50%
                    $purse = $plugin->getPurse($name);
                    $half = (int)floor($purse / 2);
                    if ($half > 0) {
                        $plugin->addBank($name, $half);
                        $plugin->setPurse($name, $purse - $half);
                        $bank = $plugin->getBank($name);
                        $player->sendMessage("§aDeposited §6" . self::formatShortNumber($half) . " coins§a! Theres now §6" . self::formatShortNumber($bank) . " coins §ain bank account!");
                    } else {
                        $player->sendMessage("§cNot enough money in purse to deposit 50%!");
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
                            if ($amount > 0 && $amount <= $purse) {
                                $plugin->addBank($name, $amount);
                                $plugin->setPurse($name, $purse - $amount);
                                $bank = $plugin->getBank($name);
                                $player->sendMessage("§aDeposited §6" . self::formatShortNumber($amount) . " coins§a! Theres now §6" . self::formatShortNumber($bank) . " coins §ain bank account!");
                            } else {
                                $player->sendMessage("§cInvalid amount or not enough money in purse!");
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
                    if ($purse > 0) {
                        $plugin->addBank($name, $purse);
                        $plugin->setPurse($name, 0);
                        $bank = $plugin->getBank($name);
                        $player->sendMessage("§aDeposited §6" . self::formatShortNumber($purse) . " coins§a! Theres now §6" . self::formatShortNumber($bank) . " coins§a in bank account!");
                    } else {
                        $player->sendMessage("§cNo money in purse to deposit!");
                    }
                    $player->removeCurrentWindow();
                    break;
                case 22: // Deposit 50%
                    $purse = $plugin->getPurse($name);
                    $half = (int)floor($purse / 2);
                    if ($half > 0) {
                        $plugin->addBank($name, $half);
                        $plugin->setPurse($name, $purse - $half);
                        $bank = $plugin->getBank($name);
                        $player->sendMessage("§aDeposited §6" . self::formatShortNumber($half) . " coins§a! Theres now §6" . self::formatShortNumber($bank) . " coins§a in bank account!");
                    } else {
                        $player->sendMessage("§cNot enough money in purse to deposit 50%!");
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
                            if ($amount > 0 && $amount <= $purse) {
                                $plugin->addBank($name, $amount);
                                $plugin->setPurse($name, $purse - $amount);
                                $bank = $plugin->getBank($name);
                                $player->sendMessage("§aDeposited §6" . self::formatShortNumber($amount) . " coins§a! Theres now §6" . self::formatShortNumber($bank) . " coins §ain bank account!");
                            } else {
                                $player->sendMessage("§cInvalid amount or not enough money in purse!");
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
                        $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($bank) . " coins§a! Theres now §60 coins §ain bank account!");
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
                        $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($half) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins§a in bank account!");
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
                        $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($portion) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins§a in bank account!");
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
                                $player->sendMessage("§aWithdrew §6" . self::formatShortNumber($amount) . " coins§a! Theres now §6" . self::formatShortNumber($newBank) . " coins§a in bank account!");
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
                    $player->sendMessage("§c[Bank] Closed.");
                    break;
            }
            return $transaction->discard();
        });
    }
} 
