<?php

namespace App\Enums;

/**
 * Valid community tags that users can apply to a POI.
 * Used for validation in TogglePoiTagRequest and business logic in PoiTagService.
 */
enum PoiTagEnum: string
{
    case LaptopFriendly = 'laptop_friendly';
    case Wifi = 'wifi';
    case PowerOutlets = 'power_outlets';
    case Quiet = 'quiet';
    case BudgetFriendly = 'budget_friendly';
    case TouristTrap = 'tourist_trap';
}
