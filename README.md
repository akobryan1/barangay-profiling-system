The system is designed for mysql (xaamp), as its database. It's directory was located in disk D: (where xaamp was installed). Check the JS file and API directories and change according to your htdocs' directory. This system was designed to be run locally. Should the need to use an online database to arise, check the JS and API for proper fetch() configurations.

1. Put the files into your xaamp's htdocs' directory, preferably in a designated folder (barangay_profiling) for organization.
2. Start Apache and Mysql in your xaamp control panel.
3. Use this link (http://localhost/barangay_profiling/) to run the system. If it doesn't work, change the "barangay_profiling" into your folder's name. The page will run when used in (double-clicking) in the traditional way, but it will not be able to connect with the database due to CORS policy (browser stuff).
