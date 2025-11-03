<?php
namespace BiwengerProManagerAPI\Models;

use BiwengerProManagerAPI\Models\Setting;

class League implements \JsonSerializable
{
    private $id;
    private $name;
    private $competition;
    private $scoreID;
    private $type;
    private $mode;
    private $marketMode;
    private $created;
    private $icon;
    private $cover;
    private $settings; // Now this will be a Setting object
    private $upgrades;

    public function __construct(array $data, Setting $settings = null)
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->competition = $data['competition'] ?? null;
        $this->scoreID = $data['scoreID'] ?? null;
        $this->type = $data['type'] ?? null;
        $this->mode = $data['mode'] ?? null;
        $this->marketMode = $data['marketMode'] ?? null;
        $this->created = $data['created'] ?? null;
        $this->icon = $data['icon'] ?? null;
        $this->cover = $data['cover'] ?? null;
        $this->settings = $settings; // Setting object instead of raw array
        $this->upgrades = $data['upgrades'] ?? null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getCompetition()
    {
        return $this->competition;
    }

    public function getSettings()
    {
        return $this->settings;
    }

    // --- Additional getters and setters ---
    public function getScoreID()
    {
        return $this->scoreID;
    }

    public function setScoreID($scoreID)
    {
        $this->scoreID = $scoreID;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    public function getMarketMode()
    {
        return $this->marketMode;
    }

    public function setMarketMode($marketMode)
    {
        $this->marketMode = $marketMode;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function setCreated($created)
    {
        $this->created = $created;
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function setIcon($icon)
    {
        $this->icon = $icon;
    }

    public function getCover()
    {
        return $this->cover;
    }

    public function setCover($cover)
    {
        $this->cover = $cover;
    }

    public function getUpgrades()
    {
        return $this->upgrades;
    }

    public function setUpgrades($upgrades)
    {
        $this->upgrades = $upgrades;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setCompetition($competition)
    {
        $this->competition = $competition;
    }

    public function setSettings(Setting $settings = null)
    {
        $this->settings = $settings;
    }

    /**
     * @deprecated Use getSettings()->getClauses() instead
     */
    public function getHasClauses()
    {
        return $this->settings ? $this->settings->getClauses() : false;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'competition' => $this->competition,
            'scoreID' => $this->scoreID,
            'type' => $this->type,
            'mode' => $this->mode,
            'marketMode' => $this->marketMode,
            'created' => $this->created,
            'icon' => $this->icon,
            'cover' => $this->cover,
            'settings' => $this->settings, // Will serialize the Setting object
            'upgrades' => $this->upgrades
        ];
    }
}
