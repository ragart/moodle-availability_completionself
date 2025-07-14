# Availability - Activity Self Completion

Restrict module access based on its own completion status.

## Idea

This availability condition makes it easy to restrict access to a module based on whether the module itself has been completed (or not completed) by the student. An example of this could be an activity that is being archived due to updates or changes, but must remain accessible to students that have already completed it, while preventing access to students that have not yet started it.

## Conditional availability conditions

Check the global documentation about conditional availability conditions: https://docs.moodle.org/en/Conditional_activities_settings

## Warning

- This plugin is 100% open source and has NOT been tested in Moodle Workplace, Totara, or any other proprietary software system. As long as the latter do not reward plugin developers, you can use this plugin only in 100% open source environments.
- The Moodle Mobile app relies on the user profile language and/or course language to show or hide a resource: the language selected in the app does NOT prevail.

## Requirements

This plugin requires Moodle 4.1+

## Installation

Install the plugin like any other plugin to folder `/availability/condition/completionself`
See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins

## Initial Configuration

This plugin does not need configuration after installation.

## Theme support

This plugin is developed and tested on Moodle Core's Boost theme and Boost child themes, including Moodle Core's Classic theme.

## Plugin repositories

This plugin will be published and regularly updated on Github: https://github.com/ragart/moodle-availability_completionself

## Bug and problem reports / Support requests

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.
Please report bugs and problems on Github: https://github.com/ragart/moodle-availability_completionself/issues
We will do our best to solve your problems, but please note that due to limited resources we can't always provide per-case support.

## Feature proposals

Please issue feature proposals on Github: https://github.com/ragart/moodle-availability_completionself/issues
Please create pull requests on Github: https://github.com/ragart/moodle-availability_completionself/pulls
We are always interested to read about your feature proposals or even get a pull request from you, but please accept that we can handle your issues only as feature proposals and not as feature requests.

## Moodle release support

This plugin is maintained for the latest major releases of Moodle.

## Copyright

2025 Salvador Banderas Rovira <info@salvadorbanderas.eu>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <https://www.gnu.org/licenses/>.
