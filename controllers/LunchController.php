<?php
require_once 'models/User.php';
require_once 'models/Lunch.php';

class LunchController
{
  private $user;
  private $lunch;

  public function __construct($user)
  {
    $this->user = $user;
    $this->lunch = new Lunch(
      $user->getId(),
      date('Y-m-d'),
      'Some Time',// date('H:i:s'),
      'Some Where'
    );
  }

  public function session() {
    $_SESSION['lunch_id'] = $this->lunch->getId();
    $_SESSION['lunch_date'] = $this->lunch->getDate();
    $_SESSION['lunch_time'] = $this->lunch->getTime();
    $_SESSION['lunch_location'] = $this->lunch->getLocation();
  }

  // Crear un nuevo lunch
  public function createLunch()
  {
    $id = $this->lunch->save();

    $this->lunch->setId($id);
    
    $this->session();

  }


}
