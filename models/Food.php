<?php

class Food
{
    private $id;
    private $lunchId; // Foreign key referencing Lunch
    private $lightId; // Represent a mood indicator
    private $title;
    private $size;

    // Constructor
    public function __construct($lunchId, $lightId, $title, $size)
    {
        $this->lunchId = $lunchId;
        $this->lightId = $lightId;
        $this->title = $title;
        $this->size = $size;
    }

    // Getters and Setters
    public function getId()
    {
        return $this->id;
    }

    public function getLunchId()
    {
        return $this->lunchId;
    }

    public function setLunchId($lunchId)
    {
        $this->lunchId = $lunchId;
    }

    public function getLightId()
    {
        return $this->lightId;
    }

    public function setLightId($lightId)
    {
        $this->lightId = $lightId;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function setSize($size)
    {
        $this->size = $size;
    }

    // Method to return food data as an array
    public function toArray()
    {
        return get_object_vars($this);
    }
}
