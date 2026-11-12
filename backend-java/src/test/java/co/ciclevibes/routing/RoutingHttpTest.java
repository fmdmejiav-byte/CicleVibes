package co.ciclevibes.routing;

import com.sun.net.httpserver.HttpServer;
import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.atomic.AtomicReference;
import org.junit.jupiter.api.*;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.test.web.client.TestRestTemplate;
import org.springframework.test.context.DynamicPropertyRegistry;
import org.springframework.test.context.DynamicPropertySource;
import com.fasterxml.jackson.databind.JsonNode;
import static org.assertj.core.api.Assertions.assertThat;

@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT)
class RoutingHttpTest {
    static final AtomicReference<String> body = new AtomicReference<>();
    static final AtomicReference<String> request = new AtomicReference<>();
    static HttpServer provider;
    static final String QUERY = "/internal/routes?origin_lat=11.006&origin_lng=-74.782&dest_lat=10.985&dest_lng=-74.766&alternatives=1";
    @Autowired TestRestTemplate http;

    @DynamicPropertySource
    static void properties(DynamicPropertyRegistry config) throws Exception {
        provider = HttpServer.create(new InetSocketAddress("127.0.0.1", 0), 0);
        provider.createContext("/", exchange -> {
            request.set(exchange.getRequestURI().toString());
            byte[] response = body.get().getBytes(StandardCharsets.UTF_8);
            exchange.getResponseHeaders().set("Content-Type", "application/json");
            exchange.sendResponseHeaders(200, response.length);
            try (var out = exchange.getResponseBody()) { out.write(response); }
        });
        provider.start();
        config.add("routing.osrm-url", () -> "http://127.0.0.1:" + provider.getAddress().getPort());
    }

    @AfterAll static void stopProvider() { provider.stop(0); }

    @Test void preservesGeometryStepsAndDurationForMultipleCandidates() {
        body.set("""
                {"code":"Ok","routes":[
                  {"distance":4513.6,"duration":820.6,"geometry":"_p~iF~ps|U_ulLnnqC","legs":[{"steps":[
                    {"name":"Calle 72","distance":4000,"duration":700,"maneuver":{"type":"depart"}},
                    {"name":"Calle 72","maneuver":{"type":"turn","modifier":"right"}}]}]},
                  {"distance":10000,"duration":30,"geometry":"","legs":[{"steps":null}]}]}
                """);
        var response = http.getForEntity(QUERY, JsonNode.class);
        assertThat(response.getStatusCode().value()).isEqualTo(200);
        assertThat(request.get()).contains("/route/v1/cycling/-74.782,11.006;-74.766,10.985", "alternatives=1", "steps=true");
        var routes = response.getBody().path("routes");
        assertThat(routes.size()).isEqualTo(2);
        var first = routes.get(0);
        assertThat(first.path("distance_km").asDouble()).isEqualTo(4.51);
        assertThat(first.path("duration_min").asInt()).isEqualTo(14);
        assertThat(first.path("coordinates").toString()).isEqualTo("[[38.5,-120.2],[40.7,-120.95]]");
        assertThat(first.at("/steps/0/instruction").asText()).isEqualTo("Avanza hacia Calle 72");
        assertThat(first.at("/steps/1/instruction").asText()).isEqualTo("Gira a la derecha por Calle 72");
        assertThat(first.path("summary").asText()).isEqualTo("Calle 72");
        assertThat(first.path("ascent_m").isNull()).isTrue();
        assertThat(routes.get(1).path("duration_seconds").asDouble()).isEqualTo(2400);
        assertThat(routes.get(1).path("steps").isEmpty()).isTrue();
    }

    @Test void rejectsInvalidCoordinatesBeforeCallingOsrm() {
        request.set(null);
        assertThat(http.getForEntity(QUERY.replace("11.006", "NaN"), String.class)
                .getStatusCode().value()).isEqualTo(422);
        assertThat(request.get()).isNull();
    }

    @Test void reportsMissingRoutesAndInvalidGeometry() {
        body.set("{\"code\":\"NoRoute\",\"routes\":[]}");
        assertThat(http.getForEntity(QUERY, String.class).getStatusCode().value()).isEqualTo(422);
        body.set("{\"code\":\"Ok\",\"routes\":[{\"geometry\":\"~\"}]}");
        assertThat(http.getForEntity(QUERY, String.class).getStatusCode().value()).isEqualTo(502);
    }
}
