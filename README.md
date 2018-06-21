CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended Modules
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

Bookable Entities Everywhere is a module based on BAT that allows the user to
add booking and availability functionality to any node type. Any node type may
be made bookable with BEE, with a selection of daily or hourly event
granularity. It’s also possible to configure open hours. Each node of the given
type may have single or multiple units, to handle multiple identical resources.
BEE provides an availability calendar and a basic booking creation form for each
enabled node.

 * For a full description of the module visit:
   https://www.drupal.org/project/bee

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/bee


REQUIREMENTS
------------

This module requires the following contributed modules.

 * BAT - https://www.drupal.org/project/bat
 * Office Hours - https://www.drupal.org/project/office_hours


RECOMMENDED MODULES
-------------------

It's possible to take payment using Drupal Commerce - when payments are enabled
for a content type, you may set a price per day/hour, and the reservation form
will add a booking to the user's cart.

 * Commerce - https://www.drupal.org/project/commerce


INSTALLATION
------------

 * Install the Bookable Entities Everywhere  module as you would normally
   install a contributed Drupal module. It is highly recommended to use
   composer to install BEE, as that will fetch all dependencies automatically.
   See https://www.drupal.org/project/bat#no-composer if not using composer.
   Visit https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > Structure > Content types > [Content type to
       edit] and there is now a BEE option in the vertical tab set.
    3. Select whether or not to make the entity type bookable.
    4. Select Booking Length and Availability. Save.


MAINTAINERS
-----------

 * Adrian Rollett (acrollet) - https://www.drupal.org/u/acrollet
 * Nicolò Caruso - https://www.drupal.org/u/nicola85

Supporting organizations:

 * roomify - https://www.drupal.org/roomify-online-and-open-source-reservation-solutions (Creation and on-going development + maintenance)
 * SOLUTIONS! NETWORK - https://www.drupal.org/solutions-network (Sponsored initial development of periodic availability functionality)
