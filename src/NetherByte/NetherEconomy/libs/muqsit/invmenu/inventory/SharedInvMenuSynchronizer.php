<?php

declare(strict_types=1);

namespace NetherByte\NetherEconomy\libs\muqsit\invmenu\inventory;

use NetherByte\NetherEconomy\libs\muqsit\invmenu\InvMenu;
use pocketmine\inventory\Inventory;

final class SharedInvMenuSynchronizer{

	readonly private Inventory $inventory;
	readonly private SharedInventorySynchronizer $synchronizer;
	readonly private SharedInventoryNotifier $notifier;

	public function __construct(InvMenu $menu, Inventory $inventory){
		$this->inventory = $inventory;

		$menu_inventory = $menu->getInventory();
		$this->synchronizer = new SharedInventorySynchronizer($menu_inventory);
		$inventory->getListeners()->add($this->synchronizer);

		$this->notifier = new SharedInventoryNotifier($this->inventory, $this->synchronizer);
		$menu_inventory->setContents($inventory->getContents());
		$menu_inventory->getListeners()->add($this->notifier);
	}

	public function destroy() : void{
		$this->synchronizer->getSynchronizingInventory()->getListeners()->remove($this->notifier);
		$this->inventory->getListeners()->remove($this->synchronizer);
	}
}