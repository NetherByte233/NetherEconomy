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
BankUpgrade: true           # enable bank upgrade & info buttons in GUI
AccountLimit: [50000000, 100000000, 250000000, 500000000, 1000000000, 6000000000, 60000000000]  # max bank for each tier
UpgradeAmmount: [5000000, 10000000, 25000000, 50000000, 100000000, 200000000]                  # cost to upgrade to next tier
UpgradeItems:
  - null
  - null
  - {id: "minecraft:gold_block", count: 100}   # Example: 100 gold blocks for Silver tier
  - null
  - null
  - null
  - null
InterestTime: 60            # Interest payout interval in minutes
accounts:
  starter:
    brackets:
      - { max: 10000000, rate: 0.02 }
      - { max: 15000000, rate: 0.01 }
    max_interest: 250000
  bronze:
    brackets:
      - { max: 15000000, rate: 0.02 }
      - { max: 20000000, rate: 0.015 }
    max_interest: 400000
  # Add more tiers as needed
```
- Interest is paid to the bank account every `InterestTime` **minutes**.
- Each account tier can have custom interest brackets and a max payout.
- Example: For starter, 17M balance yields 10M*2% + 5M*1% = 250,000 (max payout).
- Interest transactions are logged as `By Bank Interest`.
- If `BankUpgrade` is `false`, bank limit is unlimited for all players.
- If `BankUpgrade` is `true`, new players start at "Starter Account" (limit: 50,000,000). Upgrading increases their bank limit.
- Server owners can change limits and upgrade costs by editing `config.yml`.
- **Note:** Player purse is always unlimited, only bank is limited by account tier.

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
  <img src="https://github.com/NetherByte233/images/blob/main/DepositHalf.jpg?raw=true" width="45%" />
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
