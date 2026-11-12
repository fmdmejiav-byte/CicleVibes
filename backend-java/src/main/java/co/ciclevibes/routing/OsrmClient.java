package co.ciclevibes.routing;

import com.fasterxml.jackson.databind.JsonNode;
import java.time.Duration;
import java.util.ArrayList;
import java.util.List;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.HttpStatus;
import org.springframework.http.client.SimpleClientHttpRequestFactory;
import org.springframework.stereotype.Service;
import org.springframework.web.client.RestClient;
import org.springframework.web.client.RestClientException;
import org.springframework.web.server.ResponseStatusException;

@Service
class OsrmClient {
    private final RestClient client;
    private final String profile;
    private final double averageSpeed;
    private final double maximumSpeed;

    OsrmClient(RestClient.Builder builder, @Value("${routing.osrm-url}") String baseUrl,
               @Value("${routing.profile}") String profile, @Value("${routing.average-speed}") double averageSpeed,
               @Value("${routing.maximum-speed}") double maximumSpeed) {
        if (!Double.isFinite(averageSpeed) || averageSpeed <= 0 || !Double.isFinite(maximumSpeed) || maximumSpeed <= 0) {
            throw new IllegalArgumentException("Las velocidades deben ser positivas y finitas");
        }
        var factory = new SimpleClientHttpRequestFactory();
        factory.setConnectTimeout(Duration.ofSeconds(3));
        factory.setReadTimeout(Duration.ofSeconds(22));
        this.client = builder.baseUrl(baseUrl).requestFactory(factory).build();
        this.profile = profile;
        this.averageSpeed = averageSpeed;
        this.maximumSpeed = maximumSpeed;
    }

    List<Route> calculate(double originLat, double originLng, double destLat, double destLng, int alternatives) {
        String coordinates = originLng + "," + originLat + ";" + destLng + "," + destLat;
        try {
            var response = client.get().uri(builder -> builder.path("/route/v1/{profile}/")
                    .path(coordinates).queryParam("steps", true).queryParam("overview", "full")
                    .queryParam("geometries", "polyline").queryParam("alternatives", alternatives)
                    .build(profile)).retrieve().body(JsonNode.class);
            if (response == null || !"Ok".equals(response.path("code").asText())
                    || !response.path("routes").isArray() || response.path("routes").isEmpty()) {
                throw new ResponseStatusException(HttpStatus.UNPROCESSABLE_ENTITY, "Ruta no disponible");
            }
            List<Route> routes = new ArrayList<>();
            for (JsonNode candidate : response.path("routes")) {
                routes.add(Route.from(candidate, routes.size() + 1, profile, averageSpeed, maximumSpeed));
            }
            return routes;
        } catch (RestClientException | IllegalArgumentException e) {
            throw new ResponseStatusException(HttpStatus.BAD_GATEWAY, "Respuesta de OSRM no disponible", e);
        }
    }
}
