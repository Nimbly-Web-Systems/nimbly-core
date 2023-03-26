Building the docker image
-------------------------
Change directory to `misc/docker-nimbly`and type `docker build -t nimbly .` to build the docker image, naming it "nimbly". 
You only need to build the docker container image once or if the source files of the docker image changed. 
Wait the build to finish verifying the console output does not show errors.
 
Running the docker image
------------------------
Change directory to `misc` and type: `docker compose up` to start the webserver


Installation Script
-------------------
The first time you run the source, go to [http://localhost/install.php](http://localhost/install.php) to make a super user account and setup the directory structure. This needs to be done only once. 
