; bee testing makefile

api = 2
core = 8.x
projects[drupal][version] = 8.9.1

defaults[projects][subdir] = contrib

; Pull fullcalendar_library
projects[fullcalendar_library][type] = module
projects[fullcalendar_library][download][type] = git
projects[fullcalendar_library][download][url] = https://github.com/Roomify/fullcalendar_library.git
projects[fullcalendar_library][download][branch] = 8.x-1.x

; Pull the latest version of bat
projects[bat][type] = module
projects[bat][download][type] = git
projects[bat][download][url] = https://github.com/Roomify/bat_drupal.git
projects[bat][download][branch] = 8.x-1.x
projects[bat][subdir] = bee

; Pull the latest version of bat_api
projects[bat_api][type] = module
projects[bat_api][download][type] = git
projects[bat_api][download][url] = https://github.com/Roomify/bat_api.git
projects[bat_api][download][branch] = 8.x-1.x
projects[bat_api][subdir] = bee

; +++++ Contrib Modules +++++

projects[business_rules][version] = 1.0-beta10
projects[commerce][version] = 2.19
projects[office_hours][version] = 1.3
projects[services][version] = 4.0-beta5
projects[token][version] = 1.7
projects[webform][version] = 5.18

; +++++ Libraries +++++

; colorpicker
libraries[colorpicker][directory_name] = colorpicker
libraries[colorpicker][type] = library
libraries[colorpicker][destination] = libraries
libraries[colorpicker][download][type] = get
libraries[colorpicker][download][url] = http://www.eyecon.ro/colorpicker/colorpicker.zip

; fullcalendar
libraries[fullcalendar][directory_name] = fullcalendar
libraries[fullcalendar][type] = library
libraries[fullcalendar][destination] = libraries
libraries[fullcalendar][download][type] = get
libraries[fullcalendar][download][url] = https://github.com/arshaw/fullcalendar/releases/download/v3.10.0/fullcalendar-3.10.0.zip

; scheduler
libraries[scheduler][directory_name] = fullcalendar-scheduler
libraries[scheduler][type] = library
libraries[scheduler][destination] = libraries
libraries[scheduler][download][type] = get
libraries[scheduler][download][url] = https://github.com/fullcalendar/fullcalendar-scheduler/releases/download/v1.10.1/fullcalendar-scheduler-1.10.1.zip
