# tuya-cloud-api
A class library to communicate with the Tuya IoT Cloud Platform. Currently written in PHP with the goal to translate it to Javascript and Python
## Usage
- Ensure you have requested API access from the Tuya IoT backend.
- Instantiate the class, including your `schema`, `client_id` and `secret` from the Tuya backend.
- Call `requestToken` to assign a new access token to the class. You can then continue to make additional class calls.
### Methods
`getCountries()` returns a list of countries compatible with the Tuya API.
`getUser($username, $password)` returns a user's object, including their Tuya UID on successful username and password authentication.
### TODO
- Error trapping for the `doRequest` function
- GeoIP integration for the getCountries function
- Additional API integrations for other Cloud Platform functionality
- Additional data in a GET request
- PUT and DELETE request type handling
- Token refresh handling