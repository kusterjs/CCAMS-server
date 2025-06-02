# CCAMS (Centralised code assignment and management system) server backend

This is the server backend for the CCAMS environment, holding the configuration of the squawk ranges and serving as a central database to manage and assign transponder codes.

## Introduction
The server acts as the central interface to manage all transponder code assignments. It will evaluate any request received based on the configuration of FIR and airport code ranges. The main service user is the [EuroScope CCAMS plugin for VATSIM](https://github.com/kusterjs/CCAMS). But the service is open to any kind of plugin/(ATC) client. Please open an [issue](https://github.com/kusterjs/CCAMS-server/issues) if you want to add another client to use the service.

This server backend is used to:
* manage preferrential code ranges for airports, (partial) FIRs or specific controller callsigns
* manage all requests for transponder codes at a central interface
* validate and verify requests based on different parameters
* keep a single list of reserved codes
* collect assigned squawk codes from different sources (vatsim-data, controllers sending a request)

You can review the current configuration status and the latest usage statistics on https://ccams.kilojuliett.ch/.

### What parameters are considered?
In order to determine the most appropriate transponder code for a specific flight, the server will use information from the plugin sent including:
* the controller call sign
* origin
* destination
* flight rule
* aircraft position
* the controller connection type

The server will identify the next available transponder code (ascending in the matching code range) based on the airport (1st priority) and FIR (2nd priority) transponder code list, excluding:
* any non-discrete (ending with 00) codes
* any code already used by a pilot on the network (using the VATSIM JSON snapshot)
* any code already used reported by an incoming request
* any code assigned to an aircraft and reported by an incoming request

For departing aircraft, the [VATSpy](https://github.com/vatsimnetwork/vatspy-data-project) will be used to determine the FIR range if no airport range is defined. If the aiport and FIR transponder codes are all exhausted or if no matching entry in any of these lists can be found, a random transponder code outside the preferential range will be used.

## Changes / Improvements / Reports
To ensure compliance with regional and local transponder code ranges and schemes, local ops/tech staff may request a configuration change by:
* creating an [issue](https://github.com/kusterjs/CCAMS-server/issues) for this repo; or
* directly start a [pull request](https://github.com/kusterjs/CCAMS-server/pulls) by editing the files in the folder [config](https://github.com/kusterjs/CCAMS-server/tree/main/config) as required

The issue or pull requests will be closed upon implementation on the live environment.

Any other reports regarding functionalities or fixes are welcome!