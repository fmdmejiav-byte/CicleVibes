# Cálculo de rutas con Spring Boot

El driver opcional `spring` envía las consultas de rutas de Laravel a un servicio Java 21 en el mismo equipo. Spring consulta OSRM, decodifica la geometría a pares [latitud, longitud], traduce las maniobras y ajusta el tiempo con las velocidades configuradas. Laravel mantiene la sesión, los límites de peticiones, las etiquetas, la prioridad de ciclorutas y la clasificación de perfiles. El mapa consume `GET /api/maps/profiles`. La ruta recomendada se puede probar por separado en `GET /api/maps/route`.

## Probar desde Windows

Con JDK 21 instalado, abre PowerShell en `backend-java` y ejecuta:

```powershell
.\mvnw.cmd test
.\mvnw.cmd spring-boot:run
```

Por defecto Spring escucha en `127.0.0.1:8080`. En la terminal de Spring puedes definir antes de arrancar `$env:MAP_OSRM_URL`, `$env:MAP_OSRM_PROFILE`, `$env:BIKE_AVG_SPEED_KMH` y `$env:BIKE_ROUTE_MAX_EXPECTED_SPEED_KMH` para igualar la configuración de Laravel. Usa un servidor OSRM con datos procesados para bicicleta para validar recorridos ciclistas reales. La palabra «cycling» en la URL por sí sola no determina el perfil con el que se procesaron los datos.

En el `.env` local de Laravel configura:

```dotenv
MAP_ROUTING_DRIVER=spring
SPRING_ROUTING_URL=http://127.0.0.1:8080
```

Desde la raíz de Laravel ejecuta `php artisan config:clear`, inicia la aplicación como de costumbre, entra con tu usuario y calcula una ruta en el mapa. También puedes abrir esta dirección en el navegador autenticado (ajusta el puerto si corresponde):

```text
http://127.0.0.1:8000/api/maps/route?origin_lat=11.006&origin_lng=-74.782&dest_lat=10.985&dest_lng=-74.766
```

Con Spring detenido, Laravel trata el cálculo como no disponible. Para volver al motor anterior restaura el valor previo de `MAP_ROUTING_DRIVER` y ejecuta `php artisan config:clear`. El servicio Java está pensado para uso interno: en un despliegue con contenedores distintos necesita conectividad privada entre Laravel y Spring.
