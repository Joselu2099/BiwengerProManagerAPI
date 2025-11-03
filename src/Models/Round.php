<?php
namespace BiwengerProManagerAPI\Models;

class Round implements \JsonSerializable
{
    private $id;
    private $name;
    private $status;
    private $startDate;
    private $endDate;

    public function __construct($id, $name, $status = null, $startDate = null, $endDate = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->status = $status;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'start' => $this->startDate,
            'end' => $this->endDate
        ];
    }
}
