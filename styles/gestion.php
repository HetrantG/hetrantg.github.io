<?php
require 'database/database.php';

// === Liste des actions ===
$message = $error = '';
$edit_echeance = null;

// Gestion de la pagination et des filtres via POST
$limit = 15;
$page = max(1, (int)($_POST['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_POST['search'] ?? '');
$filter_statut = trim($_POST['statut'] ?? '');
$filter_dossier = trim($_POST['dossier_id'] ?? '');
$filter_client = trim($_POST['client_id'] ?? '');
$filter_date_debut = trim($_POST['date_debut'] ?? '');
$filter_date_fin = trim($_POST['date_fin'] ?? '');
$filter_montant_min = trim($_POST['montant_min'] ?? '');
$filter_montant_max = trim($_POST['montant_max'] ?? '');

// Stocker les filtres en session pour les conserver après POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['search']) || isset($_POST['statut']) || isset($_POST['dossier_id']) || 
        isset($_POST['client_id']) || isset($_POST['date_debut']) || isset($_POST['date_fin']) ||
        isset($_POST['montant_min']) || isset($_POST['montant_max'])) {
        $_SESSION['echeances_filters'] = [
            'search' => $search,
            'statut' => $filter_statut,
            'dossier_id' => $filter_dossier,
            'client_id' => $filter_client,
            'date_debut' => $filter_date_debut,
            'date_fin' => $filter_date_fin,
            'montant_min' => $filter_montant_min,
            'montant_max' => $filter_montant_max,
            'page' => $page
        ];
    }
} else {
    // Récupérer les filtres depuis la session si existants
    if (isset($_SESSION['echeances_filters'])) {
        $search = $_SESSION['echeances_filters']['search'];
        $filter_statut = $_SESSION['echeances_filters']['statut'];
        $filter_dossier = $_SESSION['echeances_filters']['dossier_id'];
        $filter_client = $_SESSION['echeances_filters']['client_id'];
        $filter_date_debut = $_SESSION['echeances_filters']['date_debut'];
        $filter_date_fin = $_SESSION['echeances_filters']['date_fin'];
        $filter_montant_min = $_SESSION['echeances_filters']['montant_min'];
        $filter_montant_max = $_SESSION['echeances_filters']['montant_max'];
        $page = $_SESSION['echeances_filters']['page'] ?? 1;
        $offset = ($page - 1) * $limit;
    }
}

// Récupérer l'agence de l'utilisateur connecté
$user_agence_id = null;
if (isset($_SESSION['utilisateur_id'])) {
    $stmt = $con->prepare("SELECT agence_id FROM utilisateurs WHERE utilisateur_id = ?");
    $stmt->execute([$_SESSION['utilisateur_id']]);
    $user_agence_id = $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // === Nouvelle Échéance ===
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            $echeance_id = trim($_POST['echeance_id']);
            $dossier_id = trim($_POST['dossier_id']);
            $numero_echeance = intval($_POST['numero_echeance']);
            $type_echeance_id = $_POST['type_echeance_id'];
            $date_echeance = $_POST['date_echeance'];
            $montant = floatval($_POST['montant']);
            $statut = $_POST['statut'] ?? 'attente';

            if (empty($echeance_id) || empty($dossier_id) || $numero_echeance <= 0 || empty($date_echeance) || $montant <= 0) {
                throw new Exception('Tous les champs obligatoires doivent être remplis correctement.');
            }

            // Vérifier si l'ID existe déjà
            $stmt = $con->prepare('SELECT COUNT(*) FROM echeances WHERE echeance_id = ?');
            $stmt->execute([$echeance_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cet ID d\'échéance existe déjà.');
            }

            $stmt = $con->prepare('INSERT INTO echeances (echeance_id, dossier_id, numero_echeance, type_echeance_id, date_echeance, montant, statut) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$echeance_id, $dossier_id, $numero_echeance, $type_echeance_id, $date_echeance, $montant, $statut]);

            $details = "Création échéance $echeance_id | Dossier: $dossier_id | Montant: $montant";
            $stmt = $con->prepare('INSERT INTO audits (utilisateur_id, action, module, details, date_action, etat_audit) VALUES (?, ?, ?, ?, NOW(), ?)');
            $stmt->execute([$_SESSION['utilisateur_id'], 'Création échéance', 'Échéances', $details, 'Succès']);
            $message = 'Échéance créée avec succès.';
        }

        // === Modifier Échéance ===
        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $echeance_id = trim($_POST['echeance_id']);
            $dossier_id = trim($_POST['dossier_id']);
            $numero_echeance = intval($_POST['numero_echeance']);
            $type_echeance_id = $_POST['type_echeance_id'];
            $date_echeance = $_POST['date_echeance'];
            $montant = floatval($_POST['montant']);
            $statut = $_POST['statut'];

            if (empty($echeance_id) || empty($dossier_id) || $numero_echeance <= 0 || empty($date_echeance) || $montant <= 0) {
                throw new Exception('Tous les champs obligatoires doivent être remplis correctement.');
            }

            $stmt = $con->prepare('UPDATE echeances SET
                echeance_id = ?, dossier_id = ?, numero_echeance = ?, type_echeance_id = ?,
                date_echeance = ?, montant = ?, statut = ?
                WHERE echeance_id = ?');
            
            $stmt->execute([
                $echeance_id, $dossier_id, $numero_echeance, $type_echeance_id,
                $date_echeance, $montant, $statut, $id
            ]);

            $details = "Modification échéance $id → $echeance_id | Dossier: $dossier_id | Montant: $montant";
            $stmt = $con->prepare('INSERT INTO audits (utilisateur_id, action, module, details, date_action, etat_audit) VALUES (?, ?, ?, ?, NOW(), ?)');
            $stmt->execute([$_SESSION['utilisateur_id'], 'Modification échéance', 'Échéances', $details, 'Succès']);
            $message = 'Échéance modifiée avec succès.';
        }

        // === Supprimer Échéance ===
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $id = $_POST['id'];
            
            $stmt = $con->prepare('SELECT statut FROM echeances WHERE echeance_id = ?');
            $stmt->execute([$id]);
            $statut = $stmt->fetchColumn();
            
            if ($statut == 'payee') {
                throw new Exception('Impossible de supprimer une échéance déjà payée.');
            }
            
            $stmt = $con->prepare('DELETE FROM echeances WHERE echeance_id = ?');
            $stmt->execute([$id]);

            $stmt = $con->prepare('INSERT INTO audits (utilisateur_id, action, module, details, date_action, etat_audit) VALUES (?, ?, ?, ?, NOW(), ?)');
            $stmt->execute([$_SESSION['utilisateur_id'], 'Suppression échéance', 'Échéances', "Suppression échéance ID $id", 'Succès']);
            $message = 'Échéance supprimée avec succès.';
        }

        // === Charger pour modification ===
        if (isset($_POST['edit_echeance_id'])) {
            $stmt = $con->prepare('
                SELECT e.*, d.dossier_id, d.montant as dossier_montant, d.statut as dossier_statut, d.objet,
                       et.nom as type_echeance_nom,
                       co.compte_id, co.numero_compte,
                       cl.client_id, cl.nom as client_nom, cl.prenom as client_prenom, cl.matricule
                FROM echeances e
                LEFT JOIN dossiers d ON e.dossier_id = d.dossier_id
                LEFT JOIN echeances_types et ON e.type_echeance_id = et.type_echeance_id
                LEFT JOIN comptes co ON d.compte_id = co.compte_id
                LEFT JOIN clients cl ON co.client_id = cl.client_id
                WHERE e.echeance_id = ?
            ');
            $stmt->execute([$_POST['edit_echeance_id']]);
            $edit_echeance = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // === Réinitialiser les filtres ===
        if (isset($_POST['reset_filters'])) {
            unset($_SESSION['echeances_filters']);
            $search = '';
            $filter_statut = '';
            $filter_dossier = '';
            $filter_client = '';
            $filter_date_debut = '';
            $filter_date_fin = '';
            $filter_montant_min = '';
            $filter_montant_max = '';
            $page = 1;
            $offset = 0;
        }

        // === Export Excel ===
        if (isset($_POST['export_excel'])) {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="echeances_' . date('Ymd_His') . '.xls"');
            
            $sql_export = 'SELECT e.echeance_id, e.numero_echeance, et.nom as type_echeance_nom, e.date_echeance, e.montant, e.statut,
                                  d.dossier_id, d.montant as dossier_montant,
                                  co.numero_compte,
                                  cl.nom as client_nom, cl.prenom as client_prenom, cl.matricule
                           FROM echeances e
                           LEFT JOIN dossiers d ON e.dossier_id = d.dossier_id
                           LEFT JOIN echeances_types et ON e.type_echeance_id = et.type_echeance_id
                           LEFT JOIN comptes co ON d.compte_id = co.compte_id
                           LEFT JOIN clients cl ON co.client_id = cl.client_id';
            
            $where_clauses = [];
            if (!empty($search)) {
                $where_clauses[] = '(e.echeance_id LIKE "%' . addslashes($search) . '%" OR d.dossier_id LIKE "%' . addslashes($search) . '%" OR cl.nom LIKE "%' . addslashes($search) . '%")';
            }
            if (!empty($filter_statut)) {
                $where_clauses[] = "e.statut = '$filter_statut'";
            }
            if (!empty($filter_dossier)) {
                $where_clauses[] = "e.dossier_id = '$filter_dossier'";
            }
            if (!empty($filter_client)) {
                $where_clauses[] = "co.client_id = '$filter_client'";
            }
            if (!empty($filter_date_debut)) {
                $where_clauses[] = "e.date_echeance >= '$filter_date_debut'";
            }
            if (!empty($filter_date_fin)) {
                $where_clauses[] = "e.date_echeance <= '$filter_date_fin'";
            }
            
            if (!empty($where_clauses)) {
                $sql_export .= ' WHERE ' . implode(' AND ', $where_clauses);
            }
            
            $sql_export .= ' ORDER BY e.date_echeance DESC';
            
            $stmt = $con->prepare($sql_export);
            $stmt->execute();
            $export_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<table border="1">';
            echo '<tr>
                    <th>ID Échéance</th>
                    <th>ID Dossier</th>
                    <th>N° Compte</th>
                    <th>Client</th>
                    <th>N° Échéance</th>
                    <th>Type</th>
                    <th>Date Échéance</th>
                    <th>Montant</th>
                    <th>Statut</th>
                  </tr>';
            
            foreach ($export_data as $row) {
                echo '<tr>';
                echo '<td>' . $row['echeance_id'] . '</td>';
                echo '<td>' . $row['dossier_id'] . '</td>';
                echo '<td>' . ($row['numero_compte'] ?? '-') . '</td>';
                echo '<td>' . $row['client_nom'] . ' ' . $row['client_prenom'] . ' (' . $row['matricule'] . ')' . '</td>';
                echo '<td>' . $row['numero_echeance'] . '</td>';
                echo '<td>' . $row['type_echeance_nom'] . '</td>';
                echo '<td>' . date('d/m/Y', strtotime($row['date_echeance'])) . '</td>';
                echo '<td>' . number_format($row['montant'], 0, ',', ' ') . ' FCFA' . '</td>';
                echo '<td>' . ucfirst($row['statut']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            exit;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Recalculer l'offset avec la page mise à jour
$offset = ($page - 1) * $limit;

// === Récupérer les listes déroulantes ===
$stmt = $con->prepare('SELECT dossier_id, montant, objet FROM dossiers ORDER BY dossier_id DESC');
$stmt->execute();
$liste_dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $con->prepare('SELECT client_id, matricule, nom, prenom FROM clients WHERE statut = "actif" ORDER BY nom, prenom');
$stmt->execute();
$liste_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $con->prepare('SELECT type_echeance_id, nom FROM echeances_types WHERE statut = "actif" ORDER BY nom');
$stmt->execute();
$types_echeance_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statuts_echeance = ['attente', 'payee', 'impayee'];

// === Compter total avec recherche et filtres ===
$count_sql = 'SELECT COUNT(*) FROM echeances e 
              LEFT JOIN dossiers d ON e.dossier_id = d.dossier_id
              LEFT JOIN echeances_types et ON e.type_echeance_id = et.type_echeance_id
              LEFT JOIN comptes co ON d.compte_id = co.compte_id
              LEFT JOIN clients cl ON co.client_id = cl.client_id';
$count_params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(e.echeance_id LIKE ? OR d.dossier_id LIKE ? OR cl.nom LIKE ? OR cl.prenom LIKE ? OR co.numero_compte LIKE ?)';
    $search_term = "%$search%";
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if (!empty($filter_statut)) {
    $where_clauses[] = 'e.statut = ?';
    $count_params[] = $filter_statut;
}

if (!empty($filter_dossier)) {
    $where_clauses[] = 'e.dossier_id = ?';
    $count_params[] = $filter_dossier;
}

if (!empty($filter_client)) {
    $where_clauses[] = 'co.client_id = ?';
    $count_params[] = $filter_client;
}

if (!empty($filter_date_debut)) {
    $where_clauses[] = 'e.date_echeance >= ?';
    $count_params[] = $filter_date_debut;
}

if (!empty($filter_date_fin)) {
    $where_clauses[] = 'e.date_echeance <= ?';
    $count_params[] = $filter_date_fin;
}

if (!empty($filter_montant_min)) {
    $where_clauses[] = 'e.montant >= ?';
    $count_params[] = $filter_montant_min;
}

if (!empty($filter_montant_max)) {
    $where_clauses[] = 'e.montant <= ?';
    $count_params[] = $filter_montant_max;
}

if (!empty($where_clauses)) {
    $count_sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}

$stmt = $con->prepare($count_sql);
$stmt->execute($count_params);
$total_echeances = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_echeances / $limit));

// === Récupérer échéances avec recherche ===
$sql = 'SELECT e.*, d.dossier_id, d.montant as dossier_montant,
               et.nom as type_echeance_nom,
               co.compte_id, co.numero_compte,
               cl.nom as client_nom, cl.prenom as client_prenom, cl.matricule
        FROM echeances e
        LEFT JOIN dossiers d ON e.dossier_id = d.dossier_id
        LEFT JOIN echeances_types et ON e.type_echeance_id = et.type_echeance_id
        LEFT JOIN comptes co ON d.compte_id = co.compte_id
        LEFT JOIN clients cl ON co.client_id = cl.client_id';
$params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(e.echeance_id LIKE ? OR d.dossier_id LIKE ? OR cl.nom LIKE ? OR cl.prenom LIKE ? OR co.numero_compte LIKE ?)';
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if (!empty($filter_statut)) {
    $where_clauses[] = 'e.statut = ?';
    $params[] = $filter_statut;
}

if (!empty($filter_dossier)) {
    $where_clauses[] = 'e.dossier_id = ?';
    $params[] = $filter_dossier;
}

if (!empty($filter_client)) {
    $where_clauses[] = 'co.client_id = ?';
    $params[] = $filter_client;
}

if (!empty($filter_date_debut)) {
    $where_clauses[] = 'e.date_echeance >= ?';
    $params[] = $filter_date_debut;
}

if (!empty($filter_date_fin)) {
    $where_clauses[] = 'e.date_echeance <= ?';
    $params[] = $filter_date_fin;
}

if (!empty($filter_montant_min)) {
    $where_clauses[] = 'e.montant >= ?';
    $params[] = $filter_montant_min;
}

if (!empty($filter_montant_max)) {
    $where_clauses[] = 'e.montant <= ?';
    $params[] = $filter_montant_max;
}

if (!empty($where_clauses)) {
    $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}

$sql .= ' ORDER BY e.date_echeance ASC LIMIT ? OFFSET ?';

$stmt = $con->prepare($sql);
$paramIndex = 1;
foreach ($params as $param) {
    $stmt->bindValue($paramIndex++, $param, PDO::PARAM_STR);
}
$stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->execute();
$echeances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Statistiques ===
$stmt = $con->prepare('SELECT statut, COUNT(*) as count, SUM(montant) as total_montant FROM echeances GROUP BY statut');
$stmt->execute();
$stats_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stats = [];
foreach ($stats_raw as $s) {
    $stats[$s['statut']] = ['count' => $s['count'], 'total_montant' => $s['total_montant']];
}

$stmt = $con->prepare('SELECT SUM(montant) as total_global, AVG(montant) as moyenne FROM echeances');
$stmt->execute();
$global_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $con->prepare('SELECT COUNT(*) as count, SUM(montant) as total FROM echeances WHERE date_echeance < CURDATE() AND statut != "payee"');
$stmt->execute();
$echeances_retard = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $con->prepare('SELECT COUNT(*) as count, SUM(montant) as total FROM echeances WHERE date_echeance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND statut != "payee"');
$stmt->execute();
$echeances_a_venir = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $con->prepare('SELECT SUM(montant) as total FROM echeances WHERE statut = "payee"');
$stmt->execute();
$total_paye = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo $_SESSION['nom_societe'] ?? 'Epencia'; ?> | Liste des Échéances</title>
    <?php include 'config/content.php'; ?>
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .card-title-cbs {
            color: #0d233a;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d233a;
            box-shadow: 0 0 0 0.25rem rgba(13, 35, 58, 0.25);
        }
        .btn-temenos {
            background-color: #0d233a;
            color: white;
            font-weight: 600;
        }
        .btn-temenos:hover {
            background-color: #05101c;
            color: white;
        }
        .btn-success-cbs {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            color: white;
            font-weight: 600;
            border: none;
        }
        .btn-success-cbs:hover {
            background: linear-gradient(135deg, #157347 0%, #0e5a3a 100%);
            color: white;
        }
        .btn-danger-cbs {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            color: white;
            font-weight: 600;
            border: none;
        }
        .btn-danger-cbs:hover {
            background: linear-gradient(135deg, #bb2d3b 0%, #a71d2a 100%);
            color: white;
        }
        .btn-outline-cbs {
            background: transparent;
            color: #0d233a;
            border: 1px solid #0d233a;
            font-weight: 600;
        }
        .btn-outline-cbs:hover {
            background: #0d233a;
            color: white;
        }
        .border-start-primary {
            border-left: 3px solid #0d233a !important;
        }
        .section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #0d233a;
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .select2-container--default .select2-selection--single {
            border-color: #dee2e6;
            border-radius: 6px;
            height: 38px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        
        /* Statistiques */
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .stat-icon.danger { background: #f8d7da; color: #842029; }
        .stat-icon.warning { background: #fff3cd; color: #856404; }
        .stat-icon.success { background: #d1e7dd; color: #0f5132; }
        .stat-icon.primary { background: #cfe2ff; color: #084298; }
        .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0d233a;
            line-height: 1.1;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 2px;
        }
        .stat-sub {
            font-size: 0.75rem;
            color: #495057;
            font-weight: 500;
        }
        
        /* Table */
        .table-cbs {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        .table-cbs thead {
            background: #0d233a;
            color: white;
        }
        .table-cbs thead th {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 10px;
            border: none;
            white-space: nowrap;
        }
        .table-cbs tbody td {
            font-size: 0.85rem;
            padding: 12px 10px;
            vertical-align: middle;
            border-color: #e9ecef;
        }
        .table-cbs tbody tr {
            transition: background 0.15s;
        }
        .table-cbs tbody tr:hover {
            background: #f8f9fa;
        }
        .table-cbs tbody tr.row-payee {
            background: #d1e7dd !important;
        }
        .table-cbs tbody tr.row-retard {
            background: #f8d7da !important;
        }
        .table-cbs tbody tr.row-aujourdhui {
            background: #fff3cd !important;
        }
        
        /* Badges */
        .badge-statut {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-attente { background: #fff3cd; color: #856404; }
        .badge-payee { background: #d1e7dd; color: #0f5132; }
        .badge-impayee { background: #f8d7da; color: #842029; }
        .badge-retard { background: #dc3545; color: white; }
        .badge-aujourdhui { background: #fd7e14; color: white; }
        
        .badge-compte {
            background: #e7f1ff;
            color: #0d233a;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            border: 1px solid #cfe2ff;
        }
        
        /* Pagination */
        .pagination .page-link {
            color: #0d233a;
            border-color: #dee2e6;
            cursor: pointer;
            font-weight: 500;
        }
        .pagination .page-item.active .page-link {
            background-color: #0d233a;
            border-color: #0d233a;
            color: white;
        }
        .pagination .page-item.disabled .page-link {
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        /* Action buttons */
        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        /* Modal */
        .modal-header-cbs {
            background: #0d233a;
            color: white;
            border: none;
        }
        .modal-header-cbs .btn-close {
            filter: brightness(0) invert(1);
        }
        
        /* Active filters pills */
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 12px;
        }
        .filter-pill {
            background: #e7f1ff;
            color: #0d233a;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #cfe2ff;
        }
        .filter-pill i {
            font-size: 0.7rem;
        }
        
        @media (max-width: 768px) {
            .stat-card { padding: 15px; }
            .stat-value { font-size: 1.1rem; }
            .stat-icon { width: 40px; height: 40px; font-size: 18px; }
        }
    </style>
</head>
<body>

<div class="container-fluid my-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h4 class="mb-0">📝 Liste des échéances</h4><div class="text-muted small">Consulter la liste des échéances des dossiers</div></div>

        <div class="d-flex gap-2">
            <button class="btn btn-temenos" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle me-1"></i>Nouvelle échéance
            </button>
            <form method="POST" class="d-inline">
                <button type="submit" name="export_excel" class="btn btn-success-cbs">
                    <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                </button>
            </form>
            <a href="<?php if(substr(((isset($_SERVER["HTTPS"]) ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].dirname($_SERVER["PHP_SELF"])),-1) =="/"){ echo (substr(((isset($_SERVER["HTTPS"]) ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].dirname($_SERVER["PHP_SELF"])), 0,-1)); }else{ echo ((isset($_SERVER["HTTPS"]) ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].dirname($_SERVER["PHP_SELF"]));} ?>/utilisateur/menu" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </div>
    </div>
    <hr>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($echeances_retard['total'] ?? 0, 0, ',', ' ') ?></div>
                    <div class="stat-label">Échéances en retard</div>
                    <div class="stat-sub"><?= $echeances_retard['count'] ?? 0 ?> échéance(s)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon warning"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($echeances_a_venir['total'] ?? 0, 0, ',', ' ') ?></div>
                    <div class="stat-label">À venir (30 jours)</div>
                    <div class="stat-sub"><?= $echeances_a_venir['count'] ?? 0 ?> échéance(s)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($total_paye ?? 0, 0, ',', ' ') ?></div>
                    <div class="stat-label">Total remboursé (FCFA)</div>
                    <div class="stat-sub"><?= $stats['payee']['count'] ?? 0 ?> échéances payées</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon primary"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($global_stats['total_global'] ?? 0, 0, ',', ' ') ?></div>
                    <div class="stat-label">Total général (FCFA)</div>
                    <div class="stat-sub">Moy: <?= number_format($global_stats['moyenne'] ?? 0, 0, ',', ' ') ?> FCFA</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <form method="POST" action="" id="filterForm">
        <div class="card p-4 mb-4 border-start-primary">
            <h5 class="card-title text-uppercase card-title-cbs mb-3">
                <i class="bi bi-funnel me-2 text-primary"></i>Filtres avancés
            </h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Recherche</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="ID échéance, dossier, client, compte...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select name="statut" id="filter_statut" class="form-select">
                        <option value="">Tous les statuts</option>
                        <?php foreach ($statuts_echeance as $s): ?>
                            <option value="<?= $s ?>" <?= $filter_statut == $s ? 'selected' : '' ?>>
                                <?= ucfirst($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dossier</label>
                    <select name="dossier_id" id="filter_dossier" class="form-select" style="width: 100%;">
                        <option value="">Tous les dossiers</option>
                        <?php foreach ($liste_dossiers as $d): ?>
                            <option value="<?= htmlspecialchars($d['dossier_id']) ?>" <?= $filter_dossier == $d['dossier_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['dossier_id'] . ' - ' . number_format($d['montant'], 0, ',', ' ') . ' FCFA') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Client</label>
                    <select name="client_id" id="filter_client" class="form-select" style="width: 100%;">
                        <option value="">Tous les clients</option>
                        <?php foreach ($liste_clients as $c): ?>
                            <option value="<?= htmlspecialchars($c['client_id']) ?>" <?= $filter_client == $c['client_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nom'] . ' ' . $c['prenom'] . ' (' . $c['matricule'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Période d'échéance</label>
                    <div class="input-group">
                        <input type="date" name="date_debut" class="form-control" value="<?= htmlspecialchars($filter_date_debut) ?>">
                        <span class="input-group-text">à</span>
                        <input type="date" name="date_fin" class="form-control" value="<?= htmlspecialchars($filter_date_fin) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Montant min</label>
                    <input type="number" name="montant_min" class="form-control" value="<?= htmlspecialchars($filter_montant_min) ?>" placeholder="Min">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Montant max</label>
                    <input type="number" name="montant_max" class="form-control" value="<?= htmlspecialchars($filter_montant_max) ?>" placeholder="Max">
                </div>
                <div class="col-md-12">
                    <div class="d-flex gap-2 justify-content-end">
                        <button type="submit" name="apply_filters" class="btn btn-temenos">
                            <i class="bi bi-search me-2"></i>Filtrer
                        </button>
                        <button type="submit" name="reset_filters" class="btn btn-outline-cbs">
                            <i class="bi bi-arrow-repeat me-1"></i>Réinitialiser
                        </button>
                    </div>
                    
                    <!-- Affichage des filtres actifs -->
                    <?php 
                    $active_filters = [];
                    if (!empty($search)) $active_filters[] = ['label' => 'Recherche', 'value' => $search];
                    if (!empty($filter_statut)) $active_filters[] = ['label' => 'Statut', 'value' => ucfirst($filter_statut)];
                    if (!empty($filter_dossier)) $active_filters[] = ['label' => 'Dossier', 'value' => $filter_dossier];
                    if (!empty($filter_client)) {
                        $client_label = '';
                        foreach ($liste_clients as $c) {
                            if ($c['client_id'] == $filter_client) {
                                $client_label = $c['nom'] . ' ' . $c['prenom'];
                                break;
                            }
                        }
                        $active_filters[] = ['label' => 'Client', 'value' => $client_label ?: $filter_client];
                    }
                    if (!empty($filter_date_debut) || !empty($filter_date_fin)) {
                        $period = '';
                        if (!empty($filter_date_debut)) $period .= 'du ' . date('d/m/Y', strtotime($filter_date_debut));
                        if (!empty($filter_date_fin)) $period .= ' au ' . date('d/m/Y', strtotime($filter_date_fin));
                        $active_filters[] = ['label' => 'Période', 'value' => $period];
                    }
                    if (!empty($filter_montant_min) || !empty($filter_montant_max)) {
                        $montant_range = '';
                        if (!empty($filter_montant_min)) $montant_range .= number_format($filter_montant_min, 0, ',', ' ') . ' FCFA';
                        if (!empty($filter_montant_max)) $montant_range .= ($montant_range ? ' à ' : '') . number_format($filter_montant_max, 0, ',', ' ') . ' FCFA';
                        $active_filters[] = ['label' => 'Montant', 'value' => $montant_range];
                    }
                    ?>
                    <?php if (!empty($active_filters)): ?>
                    <div class="active-filters">
                        <small class="text-muted me-2 align-self-center"><i class="bi bi-funnel-fill"></i> Filtres actifs :</small>
                        <?php foreach ($active_filters as $f): ?>
                            <span class="filter-pill">
                                <strong><?= $f['label'] ?>:</strong> <?= htmlspecialchars($f['value']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>

    <!-- Tableau -->
    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="card-title text-uppercase card-title-cbs mb-0" style="border: none;">
                <i class="bi bi-calendar-check me-2 text-primary"></i>Liste des échéances
                <span class="badge bg-secondary ms-2"><?= $total_echeances ?></span>
            </h5>
            <?php if (!empty($active_filters)): ?>
                <form method="POST" class="d-inline">
                    <button type="submit" name="reset_filters" class="btn btn-sm btn-outline-cbs">
                        <i class="bi bi-x-circle me-1"></i>Effacer les filtres
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-cbs align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID Échéance</th>
                        <th>Dossier</th>
                        <th>Compte</th>
                        <th>Client</th>
                        <th>N°</th>
                        <th>Type</th>
                        <th>Date échéance</th>
                        <th class="text-end">Montant</th>
                        <th class="text-center">Statut</th>
                        <th class="text-center" style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($echeances)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <i class="bi bi-inbox" style="font-size: 3rem; display: block; margin-bottom: 10px;"></i>
                                Aucune échéance trouvée.
                            </td>
                        </tr>
                    <?php else: foreach ($echeances as $e): 
                        $date_echeance = new DateTime($e['date_echeance']);
                        $aujourdhui = new DateTime();
                        $est_retard = ($date_echeance < $aujourdhui && $e['statut'] != 'payee');
                        $est_aujourdhui = ($date_echeance->format('Y-m-d') == $aujourdhui->format('Y-m-d') && $e['statut'] != 'payee');
                        $row_class = '';
                        if ($e['statut'] == 'payee') $row_class = 'row-payee';
                        elseif ($est_retard) $row_class = 'row-retard';
                        elseif ($est_aujourdhui) $row_class = 'row-aujourdhui';
                    ?>
                        <tr class="<?= $row_class ?>">
                            <td>
                                <code class="text-primary"><?= htmlspecialchars(substr($e['echeance_id'], 0, 18)) ?></code>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($e['dossier_id']) ?></strong><br>
                                <small class="text-muted"><?= number_format($e['dossier_montant'] ?? 0, 0, ',', ' ') ?> FCFA</small>
                            </td>
                            <td>
                                <?php if (!empty($e['numero_compte'])): ?>
                                    <span class="badge-compte" title="Compte: <?= htmlspecialchars($e['numero_compte']) ?>">
                                        <i class="bi bi-wallet2 me-1"></i><?= htmlspecialchars($e['numero_compte']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(($e['client_nom'] ?? '') . ' ' . ($e['client_prenom'] ?? '')) ?><br>
                                <small class="text-muted"><?= htmlspecialchars($e['matricule'] ?? '-') ?></small>
                            </td>
                            <td class="text-center fw-bold"><?= $e['numero_echeance'] ?></td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary">
                                    <?= htmlspecialchars($e['type_echeance_nom'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($e['date_echeance'])) ?>
                                <?php if ($est_retard): ?>
                                    <span class="badge badge-retard ms-1">Retard</span>
                                <?php elseif ($est_aujourdhui): ?>
                                    <span class="badge badge-aujourdhui ms-1">Aujourd'hui</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold"><?= number_format($e['montant'], 0, ',', ' ') ?> <small>FCFA</small></td>
                            <td class="text-center">
                                <span class="badge-statut badge-<?= $e['statut'] ?>">
                                    <?= ucfirst($e['statut']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="edit_echeance_id" value="<?= htmlspecialchars($e['echeance_id']) ?>">
                                        <input type="hidden" name="page" value="<?= $page ?>">
                                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                        <input type="hidden" name="statut" value="<?= htmlspecialchars($filter_statut) ?>">
                                        <input type="hidden" name="dossier_id" value="<?= htmlspecialchars($filter_dossier) ?>">
                                        <input type="hidden" name="client_id" value="<?= htmlspecialchars($filter_client) ?>">
                                        <input type="hidden" name="date_debut" value="<?= htmlspecialchars($filter_date_debut) ?>">
                                        <input type="hidden" name="date_fin" value="<?= htmlspecialchars($filter_date_fin) ?>">
                                        <input type="hidden" name="montant_min" value="<?= htmlspecialchars($filter_montant_min) ?>">
                                        <input type="hidden" name="montant_max" value="<?= htmlspecialchars($filter_montant_max) ?>">
                                        <button type="submit" class="btn btn-action btn-outline-cbs" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </form>
                                    <?php if ($e['statut'] != 'payee'): ?>
                                        <button type="button" class="btn btn-action btn-danger-cbs btn-supprimer"
                                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                data-echeance-id="<?= htmlspecialchars($e['echeance_id']) ?>"
                                                data-echeance-numero="<?= $e['numero_echeance'] ?>"
                                                data-dossier-id="<?= htmlspecialchars($e['dossier_id']) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <form method="POST" class="d-inline page-form">
                            <input type="hidden" name="page" value="<?= $page - 1 ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="statut" value="<?= htmlspecialchars($filter_statut) ?>">
                            <input type="hidden" name="dossier_id" value="<?= htmlspecialchars($filter_dossier) ?>">
                            <input type="hidden" name="client_id" value="<?= htmlspecialchars($filter_client) ?>">
                            <input type="hidden" name="date_debut" value="<?= htmlspecialchars($filter_date_debut) ?>">
                            <input type="hidden" name="date_fin" value="<?= htmlspecialchars($filter_date_fin) ?>">
                            <input type="hidden" name="montant_min" value="<?= htmlspecialchars($filter_montant_min) ?>">
                            <input type="hidden" name="montant_max" value="<?= htmlspecialchars($filter_montant_max) ?>">
                            <button type="submit" class="page-link" <?= $page <= 1 ? 'disabled' : '' ?>>
                                <i class="bi bi-chevron-left"></i> Précédent
                            </button>
                        </form>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <form method="POST" class="d-inline page-form">
                                <input type="hidden" name="page" value="<?= $i ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="statut" value="<?= htmlspecialchars($filter_statut) ?>">
                                <input type="hidden" name="dossier_id" value="<?= htmlspecialchars($filter_dossier) ?>">
                                <input type="hidden" name="client_id" value="<?= htmlspecialchars($filter_client) ?>">
                                <input type="hidden" name="date_debut" value="<?= htmlspecialchars($filter_date_debut) ?>">
                                <input type="hidden" name="date_fin" value="<?= htmlspecialchars($filter_date_fin) ?>">
                                <input type="hidden" name="montant_min" value="<?= htmlspecialchars($filter_montant_min) ?>">
                                <input type="hidden" name="montant_max" value="<?= htmlspecialchars($filter_montant_max) ?>">
                                <button type="submit" class="page-link"><?= $i ?></button>
                            </form>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <form method="POST" class="d-inline page-form">
                            <input type="hidden" name="page" value="<?= $page + 1 ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="statut" value="<?= htmlspecialchars($filter_statut) ?>">
                            <input type="hidden" name="dossier_id" value="<?= htmlspecialchars($filter_dossier) ?>">
                            <input type="hidden" name="client_id" value="<?= htmlspecialchars($filter_client) ?>">
                            <input type="hidden" name="date_debut" value="<?= htmlspecialchars($filter_date_debut) ?>">
                            <input type="hidden" name="date_fin" value="<?= htmlspecialchars($filter_date_fin) ?>">
                            <input type="hidden" name="montant_min" value="<?= htmlspecialchars($filter_montant_min) ?>">
                            <input type="hidden" name="montant_max" value="<?= htmlspecialchars($filter_montant_max) ?>">
                            <button type="submit" class="page-link" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                                Suivant <i class="bi bi-chevron-right"></i>
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nouvelle Échéance -->
<div class="modal fade" id="addModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-cbs">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nouvelle échéance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="section-title"><i class="bi bi-fingerprint me-2"></i>Identification</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">ID Échéance <span class="text-danger">*</span></label>
                            <input type="text" name="echeance_id" class="form-control" required placeholder="Ex: ECH-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dossier <span class="text-danger">*</span></label>
                            <select name="dossier_id" class="form-select" required>
                                <option value="">Sélectionnez un dossier</option>
                                <?php foreach ($liste_dossiers as $d): ?>
                                    <option value="<?= htmlspecialchars($d['dossier_id']) ?>">
                                        <?= htmlspecialchars($d['dossier_id'] . ' - ' . number_format($d['montant'], 0, ',', ' ') . ' FCFA') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-info-circle me-2"></i>Détails</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">N° Échéance <span class="text-danger">*</span></label>
                            <input type="number" name="numero_echeance" class="form-control" required min="1" placeholder="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type d'échéance <span class="text-danger">*</span></label>
                            <select name="type_echeance_id" class="form-select" required>
                                <option value="">Sélectionnez un type</option>
                                <?php foreach ($types_echeance_list as $t): ?>
                                    <option value="<?= htmlspecialchars($t['type_echeance_id']) ?>"><?= htmlspecialchars($t['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Statut</label>
                            <select name="statut" class="form-select">
                                <?php foreach ($statuts_echeance as $s): ?>
                                    <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-cash-coin me-2"></i>Informations financières</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date échéance <span class="text-danger">*</span></label>
                            <input type="date" name="date_echeance" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Montant (FCFA) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="montant" class="form-control" required min="0" placeholder="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-cbs" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-temenos">
                        <i class="bi bi-check-circle me-1"></i>Créer l'échéance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modification -->
<div class="modal fade" id="editModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-cbs">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Modifier l'échéance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="page" value="<?= $page ?>">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="statut" value="<?= htmlspecialchars($filter_statut) ?>">
                    <input type="hidden" name="dossier_id" value="<?= htmlspecialchars($filter_dossier) ?>">
                    <input type="hidden" name="client_id" value="<?= htmlspecialchars($filter_client) ?>">
                    <input type="hidden" name="date_debut" value="<?= htmlspecialchars($filter_date_debut) ?>">
                    <input type="hidden" name="date_fin" value="<?= htmlspecialchars($filter_date_fin) ?>">
                    <input type="hidden" name="montant_min" value="<?= htmlspecialchars($filter_montant_min) ?>">
                    <input type="hidden" name="montant_max" value="<?= htmlspecialchars($filter_montant_max) ?>">

                    <div class="section-title"><i class="bi bi-fingerprint me-2"></i>Identification</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">ID Échéance</label>
                            <input type="text" name="echeance_id" id="edit_echeance_id" class="form-control bg-light" required readonly style="font-family: monospace;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dossier</label>
                            <select name="dossier_id" id="edit_dossier_id" class="form-select" required>
                                <?php foreach ($liste_dossiers as $d): ?>
                                    <option value="<?= htmlspecialchars($d['dossier_id']) ?>">
                                        <?= htmlspecialchars($d['dossier_id'] . ' - ' . number_format($d['montant'], 0, ',', ' ') . ' FCFA') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-info-circle me-2"></i>Détails</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">N° Échéance</label>
                            <input type="number" name="numero_echeance" id="edit_numero_echeance" class="form-control" required min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type d'échéance</label>
                            <select name="type_echeance_id" id="edit_type_echeance_id" class="form-select" required>
                                <?php foreach ($types_echeance_list as $t): ?>
                                    <option value="<?= htmlspecialchars($t['type_echeance_id']) ?>"><?= htmlspecialchars($t['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Statut</label>
                            <select name="statut" id="edit_statut" class="form-select">
                                <?php foreach ($statuts_echeance as $s): ?>
                                    <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-cash-coin me-2"></i>Informations financières</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date échéance</label>
                            <input type="date" name="date_echeance" id="edit_date_echeance" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Montant (FCFA)</label>
                            <input type="number" step="0.01" name="montant" id="edit_montant" class="form-control" required min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-cbs" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-temenos">
                        <i class="bi bi-check-circle me-1"></i>Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-cbs" style="background: #dc3545;">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    
                    <p>Voulez-vous vraiment supprimer cette échéance ?</p>
                    <div class="info-badge">
                        <strong>Dossier :</strong> <span id="delete_dossier_id"></span><br>
                        <strong>Échéance n° :</strong> <span id="delete_echeance_numero"></span>
                    </div>
                    <p class="text-danger mb-0 mt-3"><small><i class="bi bi-exclamation-circle me-1"></i>Cette action est irréversible.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-cbs" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger-cbs">
                        <i class="bi bi-trash me-1"></i>Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ⚠️ Bootstrap DOIT être chargé AVANT votre script personnalisé -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<style>
    .info-badge {
        background: #e8f4fd;
        border-left: 4px solid #0d233a;
        padding: 10px 15px;
        border-radius: 6px;
        font-size: 0.85rem;
    }
</style>

<script>
$(document).ready(function() {
    // Initialisation Select2
    $('#filter_client, #filter_dossier').select2({
        theme: 'default',
        width: '100%',
        allowClear: true
    });
    
    // === OUVERTURE AUTOMATIQUE DU MODAL MODIFICATION ===
    <?php if ($edit_echeance): ?>
    console.log('Ouverture du modal de modification pour : <?= $edit_echeance['echeance_id'] ?>');
    
    // Remplir les champs du modal
    $('#edit_id').val('<?= addslashes($edit_echeance['echeance_id']) ?>');
    $('#edit_echeance_id').val('<?= addslashes($edit_echeance['echeance_id']) ?>');
    $('#edit_dossier_id').val('<?= addslashes($edit_echeance['dossier_id']) ?>');
    $('#edit_numero_echeance').val('<?= (int)$edit_echeance['numero_echeance'] ?>');
    $('#edit_type_echeance_id').val('<?= addslashes($edit_echeance['type_echeance_id']) ?>');
    $('#edit_date_echeance').val('<?= $edit_echeance['date_echeance'] ?>');
    $('#edit_montant').val('<?= (float)$edit_echeance['montant'] ?>');
    $('#edit_statut').val('<?= $edit_echeance['statut'] ?>');
    
    // Ouvrir le modal (Bootstrap est maintenant chargé)
    var editModal = new bootstrap.Modal(document.getElementById('editModal'));
    editModal.show();
    <?php else: ?>
    console.log('Pas de modification en cours');
    <?php endif; ?>
    
    // === MODAL SUPPRESSION ===
    document.getElementById('deleteModal')?.addEventListener('show.bs.modal', function(e) {
        let button = e.relatedTarget;
        document.getElementById('delete_id').value = button.getAttribute('data-echeance-id');
        document.getElementById('delete_echeance_numero').textContent = button.getAttribute('data-echeance-numero');
        document.getElementById('delete_dossier_id').textContent = button.getAttribute('data-dossier-id');
    });
});
</script>
</body>
</html>