<?php

declare(strict_types=1);

namespace NetherByte\NetherEconomy\libs\muqsit\invmenu\type;

use NetherByte\NetherEconomy\libs\muqsit\invmenu\inventory\InvMenuInventory;
use NetherByte\NetherEconomy\libs\muqsit\invmenu\InvMenu;
use NetherByte\NetherEconomy\libs\muqsit\invmenu\type\graphic\BlockInvMenuGraphic;
use NetherByte\NetherEconomy\libs\muqsit\invmenu\type\graphic\InvMenuGraphic;
use NetherByte\NetherEconomy\libs\muqsit\invmenu\type\graphic\network\InvMenuGraphicNetworkTranslator;
use NetherByte\NetherEconomy\libs\muqsit\invmenu\type\util\InvMenuTypeHelper;
use pocketmine\block\Block;
use pocketmine\inventory\Inventory;
use pocketmine\player\Player;

final class BlockFixedInvMenuType implements FixedInvMenuType{

	public function __construct(
		readonly private Block $block,
		readonly private int $size,
		readonly private ?InvMenuGraphicNetworkTranslator $network_translator = null
	){}

	public function getSize() : int{
		return $this->size;
	}

	public function createGraphic(InvMenu $menu, Player $player) : ?InvMenuGraphic{
		$origin = $player->getPosition()->addVector(InvMenuTypeHelper::getBehindPositionOffset($player))->floor();
		if(!InvMenuTypeHelper::isValidYCoordinate($origin->y)){
			return null;
		}

		return new BlockInvMenuGraphic($this->block, $origin, $this->network_translator);
	}

	public function createInventory() : Inventory{
		return new InvMenuInventory($this->size);
	}
}