<?php
require_once '../../recursos/conexao/index.php';
session_start();
$idEmpresa = $_SESSION['idEmpresa'];
$id_operacao = intval($_GET['id_operacao']);
$stmt = $pdo->prepare("
    SELECT c.*, u.nome as funcionario, p.nome as promotor
    FROM cambio_comissoes c
    LEFT JOIN usuarios u ON c.id_pessoa = u.id AND c.tipo_pessoa = 'FUNCIONARIO'
    LEFT JOIN promotor p ON c.id_pessoa = p.id AND c.tipo_pessoa = 'PROMOTOR'
    WHERE c.idEmpresa = ? AND c.id_operacao = ?
    ORDER BY c.id ASC
");
$stmt->execute([$idEmpresa, $id_operacao]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
