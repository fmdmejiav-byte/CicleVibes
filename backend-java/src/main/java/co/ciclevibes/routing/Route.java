package co.ciclevibes.routing;

import com.fasterxml.jackson.databind.JsonNode;
import java.util.*;

record Route(int id, double distance_m, double duration_seconds, double distance_km, long duration_min,
             List<List<Double>> coordinates, List<Step> steps, String summary, String profile, String driver,
             boolean elevation_available, List<Double> elevations, Double ascent_m, Double descent_m) {

    static Route from(JsonNode raw, int id, String profile, double averageSpeed, double maximumSpeed) {
        double meters = raw.path("distance").asDouble();
        double seconds = raw.path("duration").asDouble();
        long minutes = Math.round(seconds / 60);
        if (meters > 0 && (seconds <= 0 || meters * 3.6 / seconds > maximumSpeed)) {
            double adjusted = meters * 3.6 / averageSpeed;
            minutes = Math.round(adjusted / 60);
            seconds = Math.round(adjusted * 10) / 10.0;
        }
        var steps = new ArrayList<Step>();
        var streets = new LinkedHashSet<String>();
        for (JsonNode step : raw.path("legs").path(0).path("steps")) {
            String street = step.path("name").asText("").strip();
            if (!street.isEmpty() && streets.size() < 4) streets.add(street);
            var maneuver = step.path("maneuver");
            String instruction = Step.instruction(maneuver.path("type").asText(), maneuver.path("modifier").asText(), street);
            steps.add(new Step(instruction, step.path("distance").asDouble(), step.path("duration").asDouble()));
        }
        return new Route(id, meters, seconds, Math.round(meters / 10) / 100.0, minutes,
                decodePolyline(raw.path("geometry").asText("")), steps,
                streets.isEmpty() ? "Ruta para bicicleta" : String.join(" · ", streets),
                profile, "osrm", false, null, null, null);
    }

    private static List<List<Double>> decodePolyline(String geometry) {
        var coordinates = new ArrayList<List<Double>>();
        var chars = geometry.chars().iterator();
        int lat = 0;
        int lng = 0;
        while (chars.hasNext()) {
            lat += delta(chars);
            lng += delta(chars);
            coordinates.add(List.of(lat / 100000.0, lng / 100000.0));
        }
        return coordinates;
    }

    private static int delta(PrimitiveIterator.OfInt chars) {
        int result = 0;
        for (int shift = 0; shift <= 25 && chars.hasNext(); shift += 5) {
            int value = chars.nextInt() - 63;
            if (value < 0 || value > 63) break;
            result |= (value & 31) << shift;
            if (value < 32) return (result >>> 1) ^ -(result & 1);
        }
        throw new IllegalArgumentException("Geometría OSRM incompleta");
    }
}
