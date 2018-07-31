Hypha is a lightweight CMS for small to medium sized communities and project teams, offering the possibility to create a basic website that is easy to set up and maintain.
It is open source and doesn't rely on third party code, making it a safe tool that can be self hosted. Hypha is written in php.

The saying goes: "If you can't open it you don't own it." We'd like to add however: "It's no good if you have to use a screwdriver to simply turn it on."

Hypha's core philosophy is about empowering communities by offering its members intuitive tools rather than learning curves, all while sticking to the ethical standards embedded in open source software. For this reason Hypha is not 'packed with features' but in stead focuses on features that will cater to the basic needs of a community and can be understood by average users of the web. In this philosophy every button that is used by 10% of a community but scares off 90% is a bug, rather than a feature.

**Homepage**<br/>
The hypha homepage can be found at <a href="http://hypha.net">hypha.net</a>.

**Prerequisites**
Hypha needs Apache running, with php version 5.6 or above.

**Basic install**<br/>
The easiest way to install hypha is probably to follow the instructions on <a href="http://hypha.net/hypha.php">hypha.net/hypha.php</a>.
This script packages a fully self contained copy of hypha in a single monolithic php file. If you drop this file on a server and call it from a browser it will extract all necessary files and configure your site according to a few settings to have to enter. The script guides you through the installation process step by step.

**Advanced install though git**<br/>
1. Pull in a git clone, either by downloading a <a href="https://github.com/PlanBCode/hypha/archive/master.zip">zip file</a> and extracting it in your webfolder of choice, or by issuing a <pre>git clone https://github.com/PlanBCode/hypha.git .</pre> from the commandline in that folder.
2. For the time being you should switch to our current development branch articlePlusCSS, use <pre>git checkout articlePlusCSS</pre>.
3. Open the file 'hypha.php' in a text editor and enter a superuser name and password.
4. Set write permissions to the folder 'data/', e.g. by issuing a <pre>chmod -R 777 .</pre> on Linux.
5. Navigate to your webfolder in a browser and follow instructions from there.
