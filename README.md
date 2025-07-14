# NetherEconomy

A modern, feature-rich economy and bank plugin for PocketMine-MP 5.x+  
**Easy to use, GUI-based, and developer-friendly!**


---

## Features

- 💰 **Purse & Bank System:**  
  Players have a main balance ("purse") and a secure bank account.
- 🏦 **Beautiful Bank GUI:**  
  Double chest GUI for deposit, withdraw, and transaction history.
- 📝 **Transaction History:**  
  View your last 10 bank transactions directly in the GUI.
- ⚡ **Fast/Classic GUI Switching:**  
  Choose between instant in-place GUI updates or classic close/reopen style.
- 🪙 **Custom Amounts:**  
  Deposit/withdraw any amount, including short forms like `10k`, `1M`, `1b`, etc.
- 🎁 **Join Bonus:**  
  Optionally give new players a configurable starting bonus.
- ☠️ **Death Penalty:**  
  Optionally take a percentage of purse money when a player dies.
- 🛠️ **Developer API:**  
  Public methods for easy integration with other plugins (sell shops, rewards, etc.).
- 🔒 **OP Command:**  
  `/givecoins <player> <amount>` for admins to give coins.
- 🧑‍💻 **YAML Storage:**  
  No database required—simple YAML files for balances and transactions.

---

## Commands

| Command                | Permission                        | Description                        |
|------------------------|-----------------------------------|------------------------------------|
| `/bank`                | (everyone)                        | Opens the bank GUI                 |
| `/givecoins <player> <amount>` | nethereconomy.command.givecoins | Give coins to a player (OP/admin)  |

---

## Configuration

```yaml
FastSwitching: true         # true = instant GUI updates, false = classic style
DeathPenalty: false         # true = take % of purse on death
PenaltyPercent: 50          # % of purse to take on death
JoinBonus: false            # true = give new players a starting bonus
JoinBonusAmount: 10000      # amount to give as join bonus
```

- Edit `plugins/NetherEconomy/config.yml` to customize.

---

## Bank GUI

- **Deposit:** All, 50%, or custom amount (supports 10k, 1M, etc.)
- **Withdraw:** All, 50%, 20%, or custom amount
- **Transaction History:** See your last 10 bank actions as item lore
- **Back/Close:** Easy navigation

---

## Developer API

Other plugins can use NetherEconomy as an economy backend:

```php
/** @var NetherEconomy $economy */
$economy = $server->getPluginManager()->getPlugin("NetherEconomy");
if ($economy instanceof NetherEconomy) {
    $economy->addPurse($playerName, $amount); // Give money
    $economy->setPurse($playerName, $newAmount); // Set balance
    $balance = $economy->getPurse($playerName); // Get balance
}
```
- "Purse" is the main balance (wallet), "bank" is the secure account.
- See source for more methods: `getBank`, `setBank`, `addBank`, etc.

---

## Requirements

- PocketMine-MP 5.x or newer

---

## Installation

1. Download the latest release from [Poggit](https://poggit.pmmp.io/p/NetherEconomy).
2. Place the `.phar` in your `plugins/` folder.
3. Restart your server.

---

## Screenshots
<p align="center">
  <img src="https://github.com/NetherByte233/images/blob/main/Deposit.jpg?raw=true" width="45%" />
  <img src="https://github.com/NetherByte233/images/blob/main/Withdraw.jpg?raw=true" width="45%" />
</p>
<p align="center">
  <img src="https://github.com/NetherByte233/images/blob/main/RecentTransaction.jpg?raw=true" width="45%" />
  <img src="https://github.com/NetherByte233/images/blob/main/DepositWhole.jpg?raw=true" width="45%" />
</p>
<p align="center">
  <img src="https://github.com/NetherByte233/images/blob/main/DepositHalf.jpg.jpg?raw=true" width="45%" />
  <img src="https://github.com/NetherByte233/images/blob/main/DepositCustom.jpg?raw=true" width="45%" />
</p>
<p align="center">
  <img src="https://github.com/NetherByte233/images/blob/main/WithdrawWhole.jpg?raw=true" width="45%" />
  <img src="https://github.com/NetherByte233/images/blob/main/WithdrawHalf.jpg?raw=true" width="45%" />
</p>
<p align="center">
  <img src="https://github.com/NetherByte233/images/blob/main/Withdraw20.jpg?raw=true" width="45%" />
  <img src="https://github.com/NetherByte233/images/blob/main/WithdrawCustom.jpg?raw=true" width="45%" />
</p>
---

## License

MIT

---

## Credits

- GUI powered by [InvMenu](https://github.com/Muqsit/InvMenu)
- Forms powered by [pmforms](https://github.com/dktapps-pm-plugins/pmforms)
- Plugin by [NetherByte]

---

## Support & Suggestions

Open an issue or pull request on GitHub, or contact me on Discord!

---

**Enjoy your new economy system!** 
