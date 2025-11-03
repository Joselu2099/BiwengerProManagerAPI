<?php
namespace BiwengerProManagerAPI\Models;

class Player implements \JsonSerializable
{
    private $id;
    private $name;
    private $teamID;
    private $position;
    private $price;
    private $priceIncrement;
    private $points;

    public function __construct($id, $name, $teamID = null, $position = null, $price = 0, $priceIncrement = 0, $points = 0)
    {
        $this->id = $id;
        $this->name = $name;
        $this->teamID = $teamID;
        $this->position = $position;
        $this->price = $price;
        $this->priceIncrement = $priceIncrement;
        $this->points = $points;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'teamID' => $this->teamID,
            'position' => $this->position,
            'price' => $this->price,
            'priceIncrement' => $this->priceIncrement,
            'points' => $this->points
        ];
    }
}
