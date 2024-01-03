
![LazyMePHP](https://raw.githubusercontent.com/Peixinho/LazyMePHP/main/public/img/logo.png)

LazyMePHP is a small and easy to learn/use PHP framework that I've been using for quite some time, with success, and that has some nice features:
 - MySQL, SQLite and MSSQL support
 - Class Generation based on database tables, one Class per table
 - Basic Form Generation based on database tables, one Form per table
 - RestAPI Generation based on database tables, one API file per table

The idea behind LazyMePHP is to allow me to be lazy, so in order to help in that task, it only needs to have a proper database set up, with its tables and relations between them.

The only limitation is that you **REALLY** need to have a primary key in each table.

If the structure of the database needs to be changed (added/removed columns, whatsoever) you can always regenerate all code (with some precautions).

### Don't forget, you should only expose public folder

# How to use it:
### Clone this repository as the base project you will be building:

```
git clone https://github.com/Peixinho/LazyMePHP myAwesomeProject

#optional, but I advise to start your own git repository, for many reasons...
cd myAwesomeProject && rm -rf .git
cd src/Ext
composer update (to get dependencies)

# run initial config
php LazyMePHP config

```
It will now run the initial configurator in command line, where you have to fill the following items:

 - **Database Name** (*string*): 'myDatabase'
 - **Database User** (*string*): 'myDatabase_User'
 - **Database Password** (*string*): 'myDatabase_Password'
filling the form again .. so, to be lazy in the end)
 - **Database Host** (*string*): 'localhost'
 - **Database** (*int*): Select between MySQL, MSSQL and SQLite
 - **Application Name** (*string*): 'MyAwesomeApplication'
 - **Application Title** (*string*): 'My Awesome Application'
 - **Application Version** (*string*): '1.0'
 - **Application Description** (*string*): 'My application is gonna be awesome'
 - **Application Time Zone** (*string*): 'Europe/Lisbon'
 - **Email Support** (*string*): 'myemail@myawesomeproject.com'
 - **Nr Results In Each Collection** (*int*): 100 (All generated forms will have a paginated list, in here you define a default list count for each page)
 - **Enable Activity Log:** (*bit*): 'check or uncheck' (this will enable logging for all database changes)

# LazyMePHP Auto Generation Tools
Next, you can run
```
php LazyMePHP build
```

# Be aware before using ...!
If you are running this tool after initial generation and you've made changes to generated Forms, API or Classes (you shouldn't change your generated classes anyway), your changes could be lost, this tool is table based, so if you changed some specific table form or api, don't select it.

After this, you will have a list of your database tables, where you can select what to build and some other options:
-**d** : Changes Table Descriptors
-**c** : Build Classes
-**f** : Build Forms
-**a** : Build API
-**e**: Enable Logging
After this it will list all tables, and you can select all, by using 'a', or select one or a few, comma separated (1,4,5...) and it will build the selected option for you. This is the same for all classes, forms and api.
 - **Class**: is the core heart of LazyMePHP, without it, both Forms and APIs won't work for this table
 - **Form**: it will build some basic form utility for each table selected, that allow you to add, edit and delete data for that table. If you've ran this utiity before, and this Form already exists and you've made changes, don't check this option for this table, otherwise, you will loose all your changes.
 - **API**: it will build a RestAPI for each selected table, that allows you to GET (could be by id, or by list, POST, PUT, DELETE...). If you've ran this utiity before, and this API already exists for this table and you've made changes, don't check this option for this table, otherwise, you will loose all your changes.
 - **CSS Button, Input, Anchor and Table**: Aftert selecting the tables, you could set a css class name for each input, button, anchor and table generated automatically by the FORM option. This option is mainly to make it easier to integrate some frontend framework such as bootstrap or whatever.
 - **Replace includes, RouterForms and RouteAPI**: If you dont make any changes on these files, there isn't any reason to not let it overwrite
 - **Enable Logging**: its a feature that keeps all changed data in 3 tables, so when using this option, it will create 3 tables in your database, and keep all records there. You need to enable this in the config aswell
 
# Success
If everything went well, you will have a working index with some basic functionality.

```
php LazyMePHP serve
```
and navigate to 
```
http://localhost:8080
```

# Basic Usage
## Example

| User          |
| ------------- |
| pk Id         |
| fk CountryId  |
| Name          |
| Age           |

| Country       |
| ------------- |
| pk CountryId  |
| CountryName   |

**having pk Country.CountryId -> fk User.CountryId**

## Forms and Routes
 Every table generated will have a Form that works as follows:
 - Each Table will have a Controller File by default in /src/Controllers/[Table Name].Controller.php
 - Each Table will have 3 template files using BladeOne in /src/Views/[Table Name]/list,edit and template.blade.php
 - The file RoutingForms.php is by default in /src/Controllers/RouterForms.php is the one that defines the Routes to each Controller using simple-php-router, and each Controller requires its View file, but this should be considered boilerplate and they should be edited and placed in src/Routes/Routes.php

 ```
## Classes

Every table generated will have a class that works as follows:
 - Each Table will have a Class File by default in /src/Classes/[Table Name].php 
 - All Classes will be in namespace \LazyMePHP\Classes
 - All Class columns have getters and setters *Get*[Column Name], *Set*[Column Name]
 - All Classes have Save method, if Id is provided when constructing object:
    ```
    $user = new User(); $user->Save(); // Will act as an INSERT
    ...
    $user = new User(123); $user->Save(); // Will act as an UPDATE
    ```
 -All classes have a Delete method, if id was provided upon constructing object
 - Foreign members can be built automatically
    ```
    // Country
    $pt = new \LazyMePHP\Classes\Country();
    $pt->SetCountryName('Portugal');
    $pt->Save();
    
    // User
    $user = new \LazyMePHP\Classes\User();
    $user->SetName('Peter');
    $user->SetAge('30');
    $user->SetCountryId($pt->Getid());
    $user->Save();
    echo $user->GetId(); // e.g. 123 - id is the primary key in User Table
    
    // Retrieving user data and changing it
    $user = new \LazyMePHP\Classes\User(123);
    echo $user->GetName(); // 'Peter'
    $user->Setname('John');
    $user->Save();
    // Access Foreign members by uing Get[Column Name]Object
    echo $user->GetCountryIdObject()->GetCountryName();
    // And changing it
    $user->GetCountryIdObject()->SetCountry('England'); // Of course, you are changing Country Name in Country Table
    $user->GetCountryIdObject()->Save();
    
    # Not building Foreign members
    $user = new \LazyMePHP\Classes\User(5, false); // this won't query foreign tables

    ```
 - Every class will have a *table*_list class, that allows you to select a list of that class type
 - Every List have a *FindBy*[Foreign Column Name], *FindBy*[Foreign Column Name]*Like*, *OrderBy*[Foreign Column name], *GroupBy*[Foreign Column], *Limit*
    ```
    $users = new \LazyMePHP\Classes\User_List();
    $users->FindByNameLike('John');
    $users->OrderByAge();
    // As in regular classes, you can or not build foreign tables, by default is building them
    foreach($users->GetList() as $u) { echo $u->GetName(); echo $u->GetCountryIdObject()->GetCountryName(); }
    
    // Not building foreign members
    foreach($users->GetList(false) as $u) ...
    
    ```
 

## API
 Every table generated will have some routes created in src/api/RouteAPI.php, and they make use of the controllers of each table
 - Accessible from /api/[Table Name]/ (.htaccess for apache, didnt bother with others)

 ```
 # all users
 http://localhost:8080/api/user/ # will output all users information in json format
 
 # Search specific users based on column filters
 http://localhost:8080/api/User/?FindByNameLike=John&Limit=10 # will output all users information in json format that matches criteria and Limits to 10
 
 # Specific user
 http://localhost:8080/api/User/123 # will output user 123 information in json format

### ohh but this way you expose all data, like passwords and other stuff
 yeap, thats true! However, you can configure it to expose only the columns you want (all by default) by editing the file
 ```
 /api/src/RouteAPI.php
```
that is generated by the utilities. For each table, an array of columns is created and hardcoded in that file, and if the column name is present inside that array, the data is exposed, otherwise its not shown, and BTW, security is not a responsability of LazyMePHP.


### Logging
 When this option is enabled, 3 tables are added to your database that will register every change made to the database. The only configuration that its needed to be done is to edit
 ```
 /src/Configuration/Configurations.php
```
and define whats the user auth to be registered as the owner of the change in the database
 ```
// ACTIVITY LOG
$CONFIG['APP_ACTIVITY_LOG']=1;
$CONFIG['APP_ACTIVITY_AUTH']=$_SESSION['user_logged'];
```
Viewer is under /logging and shows the list of requests ordered by date desc, and there are some filters that can be used.
This is not a full featured viewer, you can see it as an example to expand on (but it does work quite well)

# License
MIT
