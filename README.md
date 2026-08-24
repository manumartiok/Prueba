# Proyecto 
Sistema de reservas, con manejo de fechas y horarios, y visualizacion de disponibilidad.

# Instrucciones para levantar de forma local 

# Instalar dependencias
composer install

# Configurar variables de entorno
cp .env.example .env

DB_CONNECTION=mysql

DB_HOST=127.0.0.1

DB_PORT=3306

DB_DATABASE=prueba_msi

DB_USERNAME=root

DB_PASSWORD=

CACHE_STORE=file, para que maneje la cache en la memoria del proyecto y no con la base de datos

# Ejecutar migraciones y seeders
php artisan migrate --seed

# Levantar servidor
php artisan serve

# Logica del proyecto
* Registro de reservas de mesas por fecha y hora
* Asignacion automatica, validando disponibilidad horaria y de reservas previas
* Asignacion por orden de prioridad, con un maximo de 3 mesas de combinacion
* Distintos horarios de disponibilidad segun el dia
* Duracion de 2 horas por reservas y 15 minutos de antelacion para realizarla
* Cache en memoria del proyecto y para evitar multiples consultas a la base de datos

# Decisiones tomadas
* El proyecto apuntaria a un usuario que realiza la reserva de los clientes
* Posibilidad de ademas de creacion, permita editar y eliminar reservas
* Cantidad de ubicaciones, cantidad de mesas, y de personas por mesa, predefinidas con seeders al no tener un ABM
* Disponibilidad de mesas visible al seleccionar una fecha y hora de reserva
* Visualizacion de reservas al seleccionar una fecha, con orden de ubicacion y prioridad horaria
* En caso de que la reserva solicitada sea en un horario donde las 2 horas excederia horario de cierre, se tomaria igualmente y no completaria las 2 horas.

# ESTRUCTURA DEL PROYECTO 

# Migraciones 

* # tabla de ubicaciones con nombre y orden
database\migrations\2026_08_19_000001_create_ubicaciones_table.php 

* # tabla de mesas relacionada a ubicacion, con un numero y cantidad de personas 
database\migrations\2026_08_19_000002_create_mesas_table.php

* # tabla de reservas con informacion del cliente y de la reserva, relacionada a ubicaciones
database\migrations\2026_08_19_000003_create_reservas_table.php

* # tabla pivot, donde se le asigna la reservar a la mesa
database\migrations\2026_08_19_000004_create_reserva_mesa_table.php

Mesas y ubicaciones completandose con el seeder

# Modelos

* Ubicacion, relacion de con mesa y metodo de orden

* Mesa, relacion con ubicacion y reserva

* Reserva, relacion ubicacion y mesa, con cast de fecha.

# Services

* HorarioValidator, valida que le horario de la reserva este durante el rango disponible, y que cumpla con el tiempo de anticipacion y la duracion de la reserva

* DisponibilidadService, maneja la disponibilidad de las mesas cuando se va a reservar, usando memoria cache para no hacer constantes consultas a la DB

* ReservaService, encargado de crear y manejar la reserva, usando los service de horario y disponibilidad

# HTTP
* ReservaRequest, valida los datos de la dia y hora de la reserva y datos del cliente
* ReservaController, maneja la logica de los endpoints para la carga, edicion, eliminacion y visibilidad de las reservas.




