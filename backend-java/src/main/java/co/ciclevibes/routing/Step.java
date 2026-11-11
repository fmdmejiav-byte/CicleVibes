package co.ciclevibes.routing;

import java.util.Map;

record Step(String instruction, double distance_m, double duration_seconds) {
    private static final Map<String, String> DIRECTIONS = Map.of(
            "left", "a la izquierda", "right", "a la derecha",
            "sharp left", "pronunciadamente a la izquierda", "sharp right", "pronunciadamente a la derecha",
            "slight left", "ligeramente a la izquierda", "slight right", "ligeramente a la derecha",
            "straight", "de frente", "uturn", "en U");

    static String instruction(String type, String modifier, String street) {
        String direction = DIRECTIONS.getOrDefault(modifier, "");
        String via = street.isEmpty() ? "" : " por " + street;
        return switch (type) {
            case "depart" -> "Avanza hacia " + (street.isEmpty() ? "tu destino" : street);
            case "arrive" -> "Has llegado a tu destino";
            case "turn", "new name" -> "Gira " + direction + via;
            case "continue", "merge", "on ramp", "off ramp", "fork" -> "Continúa " + direction + via;
            case "roundabout", "rotary" -> "Toma la rotonda y sal" + (direction.isEmpty() ? "" : " " + direction);
            case "end of road" -> "Al final de la vía, gira " + direction;
            case "use lane" -> "Mantén el carril";
            case "notification" -> "Sigue las indicaciones";
            default -> "Continúa" + via;
        };
    }
}
