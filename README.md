# CCAMS (Centralised code assignment and management system) server backend

This is the server backend for the [EuroScope CCAMS plugin for VATSIM](https://github.com/kusterjs/CCAMS). It serves as a central database to manage and assign squawk codes requested via the plugin in EuroScope. It also holds the configuration of the squawk ranges.

## Introduction
This server backend is used to:
* collect assigned squawk codes from different sources (vatsim-data, controllers sending a request)
* validate and verify any requests based on different parameters
* manage preferrential code ranges for airports, (partial) FIRs or specific controller callsigns
* manage all requests for transponder codes at a central interface
* keep a single list of reserved codes

The server acts as the central interface to manage all transponder code assignments. It will evaluate any request received via the plugin based on the configuration of FIR and airport code ranges. You can review the current configuration status and the latest usage statistics on https://ccams.kilojuliett.ch/.

### What parameters are considered?
In order to determine the most appropriate transponder code for a specific flight, the server will use information from the plugin sent including:
* the controller call sign
* origin
* destination
* flight rule
* aircraft position
* the controller connection type

The server will identify the next available transponder code based on the airport (1st priority) and FIR (2nd priority) transponder code list, excluding:
* any non-discrete (ending with 00) codes
* any code already used by a pilot on the network
* any code already used and detected by a plugin user
* any code assigned to an aircraft and detected by a plugin user
If the aiport and FIR transponder codes are all exhausted, or if no matching entry in any of these lists can be found, a random transponder code outside the preferential range will be used.


## Changes / Improvements / Reports
The current live server configuration is available on https://ccams.kilojuliett.ch/.

To ensure compliance with regional and local transponder code ranges and schemes, local ops/tech staff may request a configuration change by:
* creating an [issue](https://github.com/kusterjs/CCAMS-server/issues) on this GitHub; or
* directly start a [pull request](https://github.com/kusterjs/CCAMS-server/pulls) by editing the files in the folder [ranges](https://github.com/kusterjs/CCAMS-server/tree/main/ranges) as required

The issue or pull requests will be closed upon implementation on the live environment.

Any other reports regarding functionalities or fixes are welcome!