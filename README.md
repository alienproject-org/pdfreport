# PDFReport

A library that allows you to generate PDF reports from an XML template describing the document, with placeholders for data.

For general information about this project, visit: https://alienproject.org

## Introduction

### How the PDFReport library works

The library uses an XML template that describes the structure of the PDF document (for example, page size and orientation, objects to display: text, lines, rectangles, barcodes, graphics, etc.) and the data to print (via placeholders that are automatically replaced with the data).

### PHP Code Example

For very simple reports, you can create a PHP report with just 5 lines of code, as the following example:

```php
<?php
use AlienProject\PDFReport\PDFReport;
$report = new PDFReport();
$report->LoadTemplate('demo.xml');
$report->SetVar('MESSAGE', 'Current date ' . date('Y-m-d'));
$report->BuildReport();
?>
```

#### Code explanation:
- Use the "use" statement to automatically load the PDFReport class
- Create a new library instance
- Pass the XML template to the instance (using LoadTemplate or SetTemplate method)
- Define the data sources (external, such as data connectors linked to databases/files, or via variables using SetVar method)
- Generate the report using the "BuildReport" method

## Installation

Use "composer" to install the library. If Composer isn't installed in your development environment, this free tool can be [downloaded from the official website.](https://getcomposer.org/)

### Installing in a new project

Create a new folder that will contain all the files for your new PHP project. Open a command shell in this folder (the root of your PHP project) and run the command below.

A "vendor" folder will be created with all the project's dependencies/libraries. This folder will also contain the "autoload.php" file to include in your PHP project. (This is an autoloader that will allow you to use the PDFReport library without having to write any include lines. The classes will be loaded automatically before they are used.)

Two more files (composer.json, composer.lock) will also be created automatically in the root folder. The composer.json file will contain the configuration for the library loaded in the project. To upgrade to the latest version in the future, use the "composer install" command.

```bash
composer require alienproject/pdfreport
```

### Installing into an existing project

In the root folder of your existing PHP project, check if the composer.json file exists. If so, open it with a text editor or your development environment (e.g., Visual Studio Code or PHPStorm). If the file doesn't exist, you can use the command described above (the one used for new projects). Make sure the file contains the following configuration lines:

```json
{
    "require": {
        "alienproject/pdfreport": "*"
    }
}
```

Open a command shell and run the following command in the root of your PHP project, where the composer.json file is located. This command opens the composer.json file and installs or updates all libraries/packages/dependencies as specified in its configuration. In the case of the PDFReport library, the latest one published on https://packagist.org/ will be installed.

```bash
composer install
```

## Test Installation

To verify that the library is working, you can create a test page with the following PHP code that will display a simple message. This code is a standard index.php page. If you use a framework like Laravel or Symfony you must add the test code in a controller and set the route that call it. Also add the XML template file (see below).

**File: index.php**
```php
<?php
/*
 * Test url:
 * https://<your_path>/index.php
 *
 */
require_once('./vendor/autoload.php');
use AlienProject\PDFReport\PDFReport;

$label = "Hello WORLD";
$templateFileName = getcwd() . DIRECTORY_SEPARATOR . 'hello.xml';

$report = new PDFReport();
$report->LoadTemplate($templateFileName);
$report->SetVar('HELLO_MESSAGE', $label);
$report->BuildReport();
?>
```

Create an XML file in the root folder with the following code:

**File: hello.xml**
```xml
<pdf>
    <!-- *** Document info. *** -->
    <info>
        <creator>Alien Project</creator>
        <author>#MBR</author>
        <title>Hello World</title>
    </info>
    <section id="main" page="A5,L">
        <print_content>hello</print_content>
        <o>
            <dest>F</dest>
            <n>page_sample_01.pdf</n>
            <isUTF8>true</isUTF8>
        </o>
    </section>
    <content id="hello">
        <!-- *** start *** -->
        <box x1="10" y1="60" x2="200" y2="85">
            <text>{HELLO_MESSAGE}</text>
            <textvertalign>Center</textvertalign>
            <texthorizalign>Center</texthorizalign>
            <border>0</border>
            <linewidth>0.25</linewidth>
            <linecolor>000000</linecolor>
            <fill type="S" color="2874a6"/>
            <font>
                <fontfamily>Helvetica</fontfamily>
                <fontsize>32</fontsize>
                <fontstyle>B</fontstyle>
                <fontcolor>FFFFFF</fontcolor>
            </font>
        </box>
        <!-- *** end *** -->
    </content>
</pdf>
```

## Version History

**Last version: 1.0.0 - Aug. 2025**

### Ver. 1.0.0 - Aug. 2025
- First release
- Data providers included:
  - PDO
  - MySqli
  - Eloquent ORM (Laravel)
  - Doctrine ORM (Symfony)

## Documentation and Interactive Testing

The complete online guide is available at: https://alienproject.org/index.php?page=help

### Interactive Testing

By accessing the reserved area, you can interactively run the example reports presented on the home page. You can modify the XML template code to test various functionalities.

**Access to the reserved area can be done quickly via:**
- **Google Authentication** (if you have a Google account)
- **Classic registration** with email address (a confirmation email will be sent to the specified address with a link to confirm the subscription)

---

For more information and examples, visit the main project website: https://alienproject.org