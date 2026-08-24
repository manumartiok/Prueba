<!-- Proyecto  -->
Sistema de reservas, con manejo de fechas y horarios, y visualizacion de disponibilidad.

<!-- Instrucciones para levantar de forma local  -->

composer install

cp .env.example .env
copiar los datos de conexion a la DB
CACHE_STORE darle valor "file", para que maneje la cache en la memoria del proyecto y no con la base de datos

php artisan migrate --seed
php artisan serve


<!-- configuraciona  la base de datos -->
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prueba_msi
DB_USERNAME=root
DB_PASSWORD=

<!-- Logica del proyecto -->
Sistema de reserva de mesas por fecha y hora, con asignacion automatica teniendo en cuenta rango de horario por dia donde se permite reservar, disponibilidad actual en cuanto a otras reservas creadas y orden de asignacion y combinacion de mesas (con maximo de 3), segun cantidad de personas que tenga la reserva. El maximo tiempo de la reserva es de 2 horas y se debe realizar con 15 minutos de anticipacion como minimo. Manejando cache de memoria para no generar reiteradas consultas a la base de datos. 

<!-- Decisiones tomadas -->
Al no tener un ABM para las mesas ni un sistema de registro y login de usuarios. Tome el enfoque de que el sistema de reservas lo usaria una persona que recibe la reserva del cliente, y lo registra, de manera que tiene acceso a todas las reservas creadas, con la posibilidad de editarla y elimminarla en caso de algun error o arrepentimiento, y las mesas estarian pre-creadas con ubicaciones, cantidad de mesas y cantidad de personas por mesa, eso asignado con los seeders. 
La visibilidad de las mesas apareceria tras aplicar los filtros de fecha y hora, y que busque en el rango horario de 2 horas, y la visibilidad de las reservas, directamente por fecha y se muestra en orden de ubicaciones de las mesas, y dentro de cada ubicacion, se muestra el orden de la reserva por prioridad horaria. 
Algo que tuve en cuenta, seria el caso de que una persona quiera realizar una reserva, pero que las 2 horas terminarian sobrepasando el horario de cierre, teniendo en cuenta que la persona que registra la reserva esta en contacto con el cliente, le comunicaria que se puede reservar, pero no se cumplirian las 2 horas de reserva, de manera que si la reserva se pide 1 hora antes del cierre, la reserva durara 1 hora. De esa manera no se perderia un cliente y el propo cliente tiene la posibilidad de decidir si quiere reservar o no, y en caso de arrepentimiento, esta la posibilidad de modificar la reserva o eliminarla.


<!-- ESTRUCTURA DEL PROYECTO  -->

<!-- Migraciones  -->

<!-- tabla de ubicaciones con nombre y orden -->
database\migrations\2026_08_19_000001_create_ubicaciones_table.php 
<!-- tabla de mesas relacionada a ubicacion, con un numero y cantidad de personas  -->
database\migrations\2026_08_19_000002_create_mesas_table.php
<!-- tabla de reservas con informacion del cliente y de la reserva, relacionada a ubicaciones -->
database\migrations\2026_08_19_000003_create_reservas_table.php
<!-- tabla pivot, donde se le asigna la reservar a la mesa -->
database\migrations\2026_08_19_000004_create_reserva_mesa_table.php

mesas y ubicaciones completandose con el seeder

<!-- Modelos -->
Ubicacion, relacion de con mesa y metodo de orden
Mesa, relacion con ubicacion y reserva
Reserva, relacion ubicacion y mesa, con cast de fecha.

<!-- Services -->
HorarioValidator, valida que le horario de la reserva este durante el rango disponible, y que cumpla con el tiempo de anticipacion y la duracion de la reserva

DisponibilidadService, maneja la disponibilidad de las mesas cuando se va a reservar, usando memoria cache para no hacer constantes consultas a la DB

ReservaService, encargado de crear y manejar la reserva, usando los service de horario y disponibilidad

<!-- HTTP -->
ReservaRequest, valida los datos de la dia y hora de la reserva y datos del cliente
ReservaController, maneja la logica de los endpoints para la carga, edicion, eliminacion y visibilidad de las reservas.

<!-- Rutas  -->
web, simplemente devuelve la vista del front
api, define las rutas que van a manejar los datos a la vista del front. Importante registrar la hoja de rutas en bootstrap/app



