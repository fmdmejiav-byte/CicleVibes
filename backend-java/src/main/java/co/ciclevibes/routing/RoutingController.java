package co.ciclevibes.routing;

import java.util.List;
import java.util.Map;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;

@RestController
class RoutingController {
    private final OsrmClient osrm;

    RoutingController(OsrmClient osrm) {
        this.osrm = osrm;
    }

    @GetMapping("/internal/routes")
    Map<String, List<Route>> routes(@RequestParam double origin_lat, @RequestParam double origin_lng,
                                   @RequestParam double dest_lat, @RequestParam double dest_lng,
                                   @RequestParam int alternatives) {
        if (!inRange(origin_lat, 90) || !inRange(dest_lat, 90)
                || !inRange(origin_lng, 180) || !inRange(dest_lng, 180)
                || alternatives < 0 || alternatives > 3) {
            throw new ResponseStatusException(HttpStatus.UNPROCESSABLE_ENTITY, "Parámetros de ruta inválidos");
        }
        return Map.of("routes", osrm.calculate(origin_lat, origin_lng, dest_lat, dest_lng, alternatives));
    }

    private static boolean inRange(double value, int limit) {
        return Double.isFinite(value) && Math.abs(value) <= limit;
    }
}
