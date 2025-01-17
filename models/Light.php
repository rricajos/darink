<?php

class Light
{
    private $id;
    private $foodId; // Foreign key referencing Food
    private $trafficLight; // A value representing the traffic light color (e.g., "red", "yellow", "green")
    private $trafficLightNote = null; // Additional note about the traffic light
    private $trafficLightEmoticon; // Emoticon representation (e.g., ":)", ":(")

    // Constructor
    public function __construct($foodId, $trafficLight, $trafficLightNote, $trafficLightEmoticon)
    {
        $this->foodId = $foodId;
        $this->trafficLight = $trafficLight;
        $this->trafficLightNote = $trafficLightNote;
        $this->trafficLightEmoticon = $trafficLightEmoticon;
    }

    // Getters and Setters
    public function getId()
    {
        return $this->id;
    }

    public function getFoodId()
    {
        return $this->foodId;
    }

    public function setFoodId($foodId)
    {
        $this->foodId = $foodId;
    }

    public function getTrafficLight()
    {
        return $this->trafficLight;
    }

    public function setTrafficLight($trafficLight)
    {
        $allowedValues = ['red', 'yellow', 'green'];
        if (!in_array($trafficLight, $allowedValues)) {
            throw new InvalidArgumentException("Invalid traffic light value.");
        }
        $this->trafficLight = $trafficLight;
    }

    public function getTrafficLightNote()
    {
        return $this->trafficLightNote;
    }

    public function setTrafficLightNote($trafficLightNote = null)
    {
        $this->trafficLightNote = $trafficLightNote;
    }

    public function getTrafficLightEmoticon()
    {
        return $this->trafficLightEmoticon;
    }

    public function setTrafficLightEmoticon($trafficLightEmoticon)
    {
        $this->trafficLightEmoticon = $trafficLightEmoticon;
    }

    // Method to return light data as an array
    public function toArray()
    {
        return get_object_vars($this);
    }
}
