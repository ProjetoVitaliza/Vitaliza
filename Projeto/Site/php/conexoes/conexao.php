<?php
function conectarBanco() {
    $servidor = "localhost";
    $usuario = "root"; // Ajuste conforme sua configuração
    $senha = ""; // Ajuste conforme sua configuração
    $banco = "ga4_vitaliza";

    $conn = new mysqli($servidor, $usuario, $senha, $banco);

    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }
    return $conn;
};

$conn = conectarBanco();
$config_valor = array_fill(0, 22, null); // Cria um array com 22 slots
$sql = "SELECT ga4_6_config_id, ga4_6_config_valor FROM ga4_6_configuracoes ORDER BY ga4_6_config_id"; // Seleciona todos os valores
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $config_valor[$row['ga4_6_config_id']] = $row['ga4_6_config_valor'];
}

$conn->close();
?>
