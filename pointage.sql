-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 25 oct. 2025 à 15:27
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `pointage`
--

-- --------------------------------------------------------

--
-- Structure de la table `attestations_salaire`
--

CREATE TABLE `attestations_salaire` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date_demande` datetime DEFAULT current_timestamp(),
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente',
  `annee` int(11) NOT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `date_traitement` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `attestations_salaire`
--

INSERT INTO `attestations_salaire` (`id`, `user_id`, `date_demande`, `statut`, `annee`, `fichier`, `date_traitement`) VALUES
(1, 2, '2025-09-18 14:09:46', 'approuve', 2025, NULL, '2025-09-18 14:32:37'),
(2, 2, '2025-09-19 08:43:59', 'approuve', 2025, NULL, '2025-09-19 08:44:24'),
(4, 2, '2025-09-21 20:50:41', 'approuve', 2025, NULL, '2025-09-21 20:51:16');

-- --------------------------------------------------------

--
-- Structure de la table `attestations_travail`
--

CREATE TABLE `attestations_travail` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date_demande` datetime DEFAULT current_timestamp(),
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente',
  `annee` int(11) NOT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `date_traitement` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `attestations_travail`
--

INSERT INTO `attestations_travail` (`id`, `user_id`, `date_demande`, `statut`, `annee`, `fichier`, `date_traitement`) VALUES
(1, 2, '2025-09-18 14:09:40', 'approuve', 2025, NULL, '2025-09-18 14:32:20'),
(2, 2, '2025-09-18 15:29:28', 'approuve', 2025, NULL, '2025-09-18 15:38:48'),
(3, 2, '2025-09-19 10:27:33', 'approuve', 2025, NULL, '2025-09-19 10:27:54'),
(4, 2, '2025-09-19 10:33:11', 'approuve', 2025, NULL, '2025-09-19 10:33:31'),
(5, 2, '2025-09-19 11:25:01', 'approuve', 2025, NULL, '2025-09-19 11:26:58');

-- --------------------------------------------------------

--
-- Structure de la table `autorisations`
--

CREATE TABLE `autorisations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `heure_sortie` time NOT NULL,
  `heure_retour` time NOT NULL,
  `motif` text NOT NULL,
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente',
  `date_demande` datetime NOT NULL,
  `date_traitement` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `autorisations`
--

INSERT INTO `autorisations` (`id`, `user_id`, `date`, `heure_sortie`, `heure_retour`, `motif`, `statut`, `date_demande`, `date_traitement`) VALUES
(1, 2, '2025-09-08', '23:37:00', '23:40:00', 'hhhhh', 'approuve', '2025-09-08 22:37:12', '2025-09-08 22:37:44'),
(2, 2, '2025-09-08', '23:48:00', '23:55:00', 'jjjjjjjj', 'approuve', '2025-09-08 22:47:41', '2025-09-08 22:48:11');

-- --------------------------------------------------------

--
-- Structure de la table `avances_salaire`
--

CREATE TABLE `avances_salaire` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_demande` datetime DEFAULT current_timestamp(),
  `motif` text DEFAULT NULL,
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente',
  `date_traitement` datetime DEFAULT NULL,
  `traite_par` int(11) DEFAULT NULL,
  `motif_refus` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `avances_salaire`
--

INSERT INTO `avances_salaire` (`id`, `user_id`, `montant`, `date_demande`, `motif`, `statut`, `date_traitement`, `traite_par`, `motif_refus`) VALUES
(2, 7, 100.00, '2025-09-25 11:26:08', 'hhhhhhhhhhhhhhhhhh', 'approuve', '2025-09-25 11:27:25', 1, NULL),
(3, 2, 700.00, '2025-09-25 11:45:06', 'hhhhhhhhhhhhhhhhh', 'approuve', '2025-09-28 20:25:48', 1, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `conges`
--

CREATE TABLE `conges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `type_conge` varchar(50) NOT NULL,
  `cause` text NOT NULL,
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente',
  `date_demande` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_traitement` timestamp NULL DEFAULT NULL,
  `traite_par` int(11) DEFAULT NULL,
  `avec_solde` tinyint(1) DEFAULT 1,
  `jours_avec_solde` tinyint(1) DEFAULT 1,
  `jours_sans_solde` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `conges`
--

INSERT INTO `conges` (`id`, `user_id`, `date_debut`, `date_fin`, `type_conge`, `cause`, `statut`, `date_demande`, `date_traitement`, `traite_par`, `avec_solde`, `jours_avec_solde`, `jours_sans_solde`) VALUES
(1, 2, '2025-09-03', '2025-09-05', 'exceptionnel', 'bonjour', 'approuve', '2025-09-03 17:26:06', '2025-09-03 17:27:24', 1, 1, 1, 0),
(2, 2, '2025-09-06', '2025-09-10', 'maladie', 'yyyyyyyyy', 'refuse', '2025-09-03 17:40:05', '2025-09-03 17:41:20', 1, 1, 1, 0),
(3, 2, '2025-09-09', '2025-09-12', 'maladie', 'hhhhhhh', 'approuve', '2025-09-03 18:02:38', '2025-09-03 18:03:35', 1, 1, 1, 0),
(4, 2, '2025-09-18', '2025-09-25', 'maternite', 'aaaaaaaaa', 'refuse', '2025-09-03 18:13:41', '2025-09-03 18:14:49', 1, 1, 1, 0),
(5, 2, '2025-09-24', '2025-09-27', 'maladie', 'aaaaaaaa', 'approuve', '2025-09-04 22:27:13', '2025-09-04 22:27:58', 1, 1, 1, 0),
(6, 2, '2025-10-01', '2025-10-03', 'maladie', 'hhhhhhhh', 'approuve', '2025-09-08 17:48:25', '2025-09-08 17:49:00', 1, 1, 1, 0),
(7, 2, '2025-09-20', '2025-09-27', 'annuel', 'jjjjjkkkk', 'approuve', '2025-09-19 10:18:06', '2025-09-19 10:23:59', 5, 1, 1, 0),
(8, 2, '2025-09-30', '2025-10-09', 'maternite', 'hhhh', 'approuve', '2025-09-19 10:25:47', '2025-09-19 10:26:21', 5, 1, 1, 0),
(9, 2, '2025-10-09', '2025-10-12', 'annuel', 'jjjjjj', 'approuve', '2025-09-21 19:52:31', '2025-09-21 19:53:04', 5, 1, 1, 0),
(10, 2, '2025-10-03', '2025-10-05', 'annuel', 'kkk', 'approuve', '2025-09-21 21:32:56', '2025-09-21 21:44:39', 1, 1, 1, 0),
(11, 2, '2025-10-15', '2025-10-16', 'annuel', 'hhhhhh', 'approuve', '2025-09-21 21:36:19', '2025-09-21 21:42:56', 1, 1, 1, 0),
(12, 7, '2025-09-21', '2025-09-23', 'maladie', 'grippe', 'approuve', '2025-09-21 21:53:18', '2025-09-29 19:53:33', 10, 1, 1, 0),
(13, 2, '2025-10-09', '2025-10-11', 'annuel', 'jjjjjj', 'refuse', '2025-09-21 21:56:52', '2025-09-21 21:58:22', 1, 1, 1, 0),
(14, 2, '2025-09-22', '2025-09-24', 'annuel', 'jjjj', 'en_attente', '2025-09-22 09:12:58', NULL, NULL, 0, 1, 0),
(15, 12, '2025-09-29', '2025-10-01', 'maladie', '222222222', 'en_attente', '2025-09-29 21:05:53', NULL, NULL, 1, 1, 0),
(16, 2, '2025-10-12', '2025-10-14', 'maternite', 'aaaaaaaaaaaaaaaaaa', 'approuve', '2025-09-30 20:54:31', '2025-09-30 20:55:14', 5, 0, 1, 0),
(17, 2, '2025-10-02', '2025-10-03', 'exceptionnel', 'jjjjjjjjjjjjjjjjjjjjjj', 'approuve', '2025-09-30 21:27:49', '2025-09-30 21:28:17', NULL, 0, 1, 0),
(18, 2, '2025-10-11', '2025-10-12', 'paternite', 'kkkkkkkkkkkkkkkkkkkkk', 'approuve', '2025-09-30 21:47:02', '2025-09-30 21:47:25', 5, 0, 1, 0),
(19, 14, '2025-10-08', '2025-10-09', 'exceptionnel', 'pleaseeeeeeeeeeeeeeeeeee', 'refuse', '2025-10-06 10:09:23', '2025-10-06 10:14:27', NULL, 0, 1, 0),
(20, 5, '2025-10-09', '2025-10-10', 'Congé exceptionnel', 'aaaaaaaaaaaaaaaaaaaaa', '', '2025-10-09 13:09:47', NULL, NULL, 1, 1, 0),
(25, 2, '2025-10-10', '2025-10-12', 'maternite', 'hhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh', 'en_attente', '2025-10-10 12:57:33', NULL, NULL, 0, 2, 2);

-- --------------------------------------------------------

--
-- Structure de la table `conges_solde`
--

CREATE TABLE `conges_solde` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  `mois` int(11) NOT NULL,
  `solde_jours` decimal(3,1) DEFAULT 1.5,
  `solde_utilise` decimal(3,1) DEFAULT 0.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `conges_solde`
--

INSERT INTO `conges_solde` (`id`, `user_id`, `annee`, `mois`, `solde_jours`, `solde_utilise`) VALUES
(1, 2, 2025, 10, 3.0, 0.0),
(2, 7, 2025, 10, 1.5, 0.0),
(3, 12, 2025, 10, 1.5, 0.0),
(4, 14, 2025, 10, 1.5, 0.0);

-- --------------------------------------------------------

--
-- Structure de la table `credits_salaire`
--

CREATE TABLE `credits_salaire` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `montant` decimal(10,2) DEFAULT NULL,
  `nombre_mensualites` int(11) DEFAULT NULL,
  `montant_mensualite` decimal(10,2) DEFAULT NULL,
  `motif` text DEFAULT NULL,
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente',
  `solde_restant` decimal(10,2) DEFAULT 0.00,
  `prochaine_echeance` date DEFAULT NULL,
  `date_remboursement_complet` date DEFAULT NULL,
  `date_demande` datetime DEFAULT current_timestamp(),
  `date_traitement` datetime DEFAULT NULL,
  `traite_par` int(11) DEFAULT NULL,
  `motif_refus` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `credits_salaire`
--

INSERT INTO `credits_salaire` (`id`, `user_id`, `montant`, `nombre_mensualites`, `montant_mensualite`, `motif`, `statut`, `solde_restant`, `prochaine_echeance`, `date_remboursement_complet`, `date_demande`, `date_traitement`, `traite_par`, `motif_refus`) VALUES
(1, 2, 1000.00, 1, 1000.00, 'hhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh', 'approuve', 1000.00, NULL, NULL, '2025-10-02 11:49:06', '2025-10-02 14:05:48', 1, NULL),
(2, 2, 500.00, 1, 500.00, 'hhhhhhhhhhhhhhhhhhhhhhhhhhhhhh', 'approuve', 500.00, NULL, NULL, '2025-10-02 14:05:19', '2025-10-02 14:05:42', 1, NULL),
(3, 5, 1000.00, 4, 250.00, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'approuve', 1000.00, NULL, NULL, '2025-10-02 15:30:23', '2025-10-02 15:33:06', 1, NULL),
(4, 14, 800.00, 8, 100.00, 'pleaseeeeeeeeeeeeeeeeeee', 'en_attente', 800.00, NULL, NULL, '2025-10-06 10:56:52', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `demandes_recrutement`
--

CREATE TABLE `demandes_recrutement` (
  `id` int(11) NOT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `poste` varchar(255) NOT NULL,
  `motivation` text DEFAULT NULL,
  `urgence` enum('normal','eleve','critique') DEFAULT 'normal',
  `fichier_pdf` varchar(255) DEFAULT NULL,
  `date_demande` datetime DEFAULT NULL,
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente',
  `date_traitement` datetime DEFAULT NULL,
  `traite_par_pdg` int(11) DEFAULT NULL,
  `envoye_au_pdg` tinyint(1) DEFAULT 0,
  `date_envoi_pdg` datetime DEFAULT NULL,
  `commentaire_pdg` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `demandes_recrutement`
--

INSERT INTO `demandes_recrutement` (`id`, `responsable_id`, `poste`, `motivation`, `urgence`, `fichier_pdf`, `date_demande`, `statut`, `date_traitement`, `traite_par_pdg`, `envoye_au_pdg`, `date_envoi_pdg`, `commentaire_pdg`) VALUES
(3, 13, 'developpeur', 'pleeeeeeeeeeeeeeeeeeeeeeeeease', 'eleve', '68e39839207ee_developpeur.pdf', '2025-10-06 11:21:45', 'refuse', '2025-10-07 14:41:17', NULL, 1, '2025-10-07 14:33:27', NULL),
(4, 5, 'dev', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '', '68e4faf35b02c_dev.pdf', '2025-10-07 12:35:15', 'refuse', '2025-10-07 14:41:24', NULL, 1, '2025-10-07 14:26:40', NULL),
(5, 5, 'assistante', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '', '68e51b1bdd236_assistante.pdf', '2025-10-07 14:52:27', 'approuve', '2025-10-07 14:55:25', 9, 0, NULL, 'bien'),
(6, 5, 'administrateur', 'pleeeeeeeeeeeeeeeease', '', '68e51ed81a1e5_administrateur.pdf', '2025-10-07 15:08:24', 'approuve', '2025-10-07 15:10:14', 9, 1, '2025-10-07 15:09:05', 'accepter');

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `timestamp`, `is_read`) VALUES
(1, 2, 1, 'hello', '2025-09-11 09:58:45', 1),
(2, 1, 2, 'bonjour', '2025-09-11 09:59:26', 0),
(3, 2, 1, 'hhhhhhhhhhhhhhhhhhh', '2025-09-11 10:01:01', 1),
(4, 1, 2, 'hhhhhhhhhhhhhhhhhh', '2025-09-11 10:01:51', 0),
(5, 2, 1, 'hhhhhhhhhhhhhhh', '2025-09-11 11:36:37', 1),
(6, 2, 1, 'hhhhhhhhhhhhhh', '2025-09-11 11:43:56', 1),
(7, 2, 1, 'aaaaaaaaaaaaaa', '2025-09-11 13:50:36', 1),
(8, 2, 1, 'ttttttt', '2025-09-11 13:52:52', 1),
(9, 2, 1, 'hello', '2025-09-14 18:47:46', 1),
(10, 2, 1, 'tttttttt', '2025-09-14 18:54:46', 1),
(11, 9, 1, 'aaaaaaaaaaaaaaaaaaaaaa', '2025-09-28 19:05:26', 1),
(12, 1, 9, 'hhhhhhhhhhhhhhhhhh', '2025-09-28 19:53:51', 1),
(13, 9, 1, 'AAAAAAAAAAAAAAAAGG', '2025-09-28 19:56:28', 1);

-- --------------------------------------------------------

--
-- Structure de la table `notes_service`
--

CREATE TABLE `notes_service` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `auteur_id` int(11) NOT NULL,
  `auteur_nom` varchar(100) NOT NULL,
  `destinataires` enum('tous','employes','responsables','admin') DEFAULT 'tous',
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notes_service`
--

INSERT INTO `notes_service` (`id`, `titre`, `contenu`, `auteur_id`, `auteur_nom`, `destinataires`, `date_creation`, `date_modification`) VALUES
(1, 'avis', 'hdjn cgehjn ek,sjkazn dsk,kl ,;,lk:sq;dslqs:m;sd;llml', 9, 'PDG', 'responsables', '2025-09-27 18:29:05', '2025-09-27 18:29:05'),
(2, 'avis au employée', 'bonjour hahsbnv,ovn djc  jfhn dsjd,  hhgdgdsnsn', 9, 'PDG', 'employes', '2025-09-28 17:25:14', '2025-09-28 17:25:14'),
(3, 'avis 1111', 'hi hgjkdkdsnxtzbnjkdigfiurtj,ikgvflkfd;mcc', 9, 'PDG', 'tous', '2025-09-28 17:47:33', '2025-09-28 17:47:33'),
(4, 'avis aaaaaa', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 9, '', 'admin', '2025-10-06 11:17:56', '2025-10-06 11:17:56');

-- --------------------------------------------------------

--
-- Structure de la table `notes_service_lus`
--

CREATE TABLE `notes_service_lus` (
  `id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date_lecture` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notes_service_lus`
--

INSERT INTO `notes_service_lus` (`id`, `note_id`, `user_id`, `date_lecture`) VALUES
(1, 1, 5, '2025-09-27 18:35:36'),
(3, 3, 1, '2025-09-28 17:54:01'),
(4, 3, 5, '2025-10-01 17:19:48'),
(10, 4, 1, '2025-10-06 11:19:21');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `date_creation` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `date_creation`) VALUES
(1, 2, 'Votre demande d\'autorisation du 2025-09-08 a été approuvée', '2025-09-08 22:37:44'),
(2, 2, 'Votre demande d\'autorisation du 2025-09-08 a été approuvée', '2025-09-08 22:48:11'),
(3, 2, 'Votre demande d\'attestation de travail pour l\'année 2025 a été approuvée', '2025-09-18 14:32:20'),
(4, 2, 'Votre demande d\'attestation de salaire pour l\'année 2025 a été approuvée', '2025-09-18 14:32:37'),
(5, 2, 'Votre demande d\'attestation de travail pour l\'année 2025 a été approuvée', '2025-09-18 15:38:48'),
(6, 2, 'Votre demande d\'attestation de salaire pour l\'année 2025 a été approuvée', '2025-09-19 08:44:24'),
(7, 2, 'Votre demande d\'attestation de salaire pour l\'année 2025 a été approuvée', '2025-09-19 09:12:40'),
(8, 2, 'Votre demande d\'attestation de travail pour l\'année 2025 a été approuvée', '2025-09-19 10:27:54'),
(9, 2, 'Votre demande d\'attestation de travail pour l\'année 2025 a été approuvée', '2025-09-19 10:33:31'),
(10, 2, 'Votre demande de congé du 2025-09-20 au 2025-09-27 a été approuvée par votre responsable.', '2025-09-19 11:23:59'),
(11, 2, 'Votre demande de congé du 2025-09-30 au 2025-10-09 a été approuvée par votre responsable.', '2025-09-19 11:26:21'),
(12, 2, 'Votre demande d\'attestation de travail pour l\'année 2025 a été approuvée', '2025-09-19 11:26:58'),
(13, 2, 'Votre demande d\'attestation de salaire pour l\'année 2025 a été approuvée', '2025-09-21 20:51:16'),
(14, 2, 'Votre demande de congé du 2025-10-09 au 2025-10-12 a été approuvée par votre responsable.', '2025-09-21 20:53:04'),
(15, 2, 'Votre demande de congé du 2025-10-03 au 2025-10-05 a été refusée par l\'administration', '2025-09-21 22:33:32'),
(16, 2, 'Votre demande de congé du 2025-10-15 au 2025-10-16 a été refusée par votre responsable.', '2025-09-21 22:36:49'),
(17, 2, 'DÉCISION FINALE - Votre demande de congé du 2025-10-15 au 2025-10-16 a été APPROUVÉE par l\'administration (décision irréversible)', '2025-09-21 22:42:56'),
(18, 2, 'DÉCISION FINALE - Votre demande de congé du 2025-10-03 au 2025-10-05 a été APPROUVÉE par l\'administration (décision irréversible)', '2025-09-21 22:44:39'),
(19, 2, 'Votre demande de congé du 2025-10-09 au 2025-10-11 a été approuvée par votre responsable.', '2025-09-21 22:57:26'),
(20, 2, 'DÉCISION FINALE - Votre demande de congé du 2025-10-09 au 2025-10-11 a été REFUSÉE par l\'administration (décision irréversible)', '2025-09-21 22:58:22'),
(21, 2, 'Votre demande d\'ordre de mission du 2025-09-23 au 2025-09-24 a été approuvée', '2025-09-23 00:17:32'),
(22, 7, 'Votre demande d\'avance de salaire de 100,00 DT a été approuvée', '2025-09-25 11:27:25'),
(23, 2, 'Votre demande d\'avance de salaire de 1 000,00 DT a été approuvée', '2025-09-25 11:27:29'),
(24, 2, 'Votre demande d\'avance de salaire de 700,00 DT a été approuvée', '2025-09-28 20:25:48'),
(25, 7, 'Votre demande de congé du 2025-09-21 au 2025-09-23 a été approuvée par votre responsable.', '2025-09-29 20:53:33'),
(26, 2, 'Votre demande de congé du 2025-10-12 au 2025-10-14 a été approuvée par votre responsable.', '2025-09-30 21:55:14'),
(27, 2, 'DÉCISION FINALE - Votre demande de congé du 2025-10-02 au 2025-10-03 a été APPROUVÉE par l\'administration (décision irréversible)', '2025-09-30 22:28:17'),
(28, 2, 'Votre demande de congé du 2025-10-11 au 2025-10-12 a été approuvée par votre responsable.', '2025-09-30 22:47:25'),
(29, 2, 'Votre demande de crédit de 500,00 DT a été approuvée', '2025-10-02 14:05:42'),
(30, 2, 'Votre demande de crédit de 1 000,00 DT a été approuvée', '2025-10-02 14:05:48'),
(31, 5, 'Votre demande de crédit de 1 000,00 DT a été approuvée', '2025-10-02 15:33:06'),
(32, 2, 'Votre demande d\'ordre de mission du  au  a été approuvée', '2025-10-03 23:56:56'),
(33, 9, 'Nouvelle demande de recrutement pour le poste de \'dev\' envoyée par Responsable', '2025-10-04 18:23:43'),
(34, 9, 'Nouvelle demande de recrutement pour le poste de \'dev\' envoyée par Responsable', '2025-10-04 18:42:11'),
(35, 5, 'Votre demande de recrutement pour le poste \'dev\' a été refusée par le PDG.', '2025-10-05 13:22:25'),
(36, 14, 'Votre demande de congé du 2025-10-08 au 2025-10-09 a été approuvée par votre responsable.', '2025-10-06 11:11:08'),
(37, 14, 'DÉCISION FINALE - Votre demande de congé du 2025-10-08 au 2025-10-09 a été REFUSÉE par l\'administration (décision irréversible)', '2025-10-06 11:13:24'),
(38, 14, 'DÉCISION FINALE - Votre demande de congé du 2025-10-08 au 2025-10-09 a été APPROUVÉE par l\'administration (décision irréversible)', '2025-10-06 11:14:06'),
(39, 14, 'DÉCISION FINALE - Votre demande de congé du 2025-10-08 au 2025-10-09 a été REFUSÉE par l\'administration (décision irréversible)', '2025-10-06 11:14:27'),
(40, 1, 'Nouvelle demande de recrutement pour le poste: dev - Responsable: Responsable', '2025-10-07 12:35:15'),
(41, 9, 'Nouvelle demande de recrutement pour le poste de \'dev\' envoyée par l\'administration', '2025-10-07 14:26:40'),
(42, 9, 'Nouvelle demande de recrutement pour le poste de \'developpeur\' envoyée par l\'administration', '2025-10-07 14:33:27'),
(43, 13, 'Votre demande de recrutement pour le poste \'developpeur\' a été refusée par le PDG.', '2025-10-07 14:41:17'),
(44, 5, 'Votre demande de recrutement pour le poste \'dev\' a été refusée par le PDG.', '2025-10-07 14:41:24'),
(45, 1, 'Nouvelle demande de recrutement pour le poste: assistante - Responsable: Responsable', '2025-10-07 14:52:27'),
(46, 1, 'Nouvelle demande de recrutement pour le poste: administrateur - Responsable: Responsable', '2025-10-07 15:08:24'),
(47, 9, 'Nouvelle demande de recrutement pour le poste de \'administrateur\' envoyée par l\'administration', '2025-10-07 15:09:05'),
(48, 9, 'Nouvelle demande de congé de Responsable () nécessitant votre approbation', '2025-10-09 14:09:47'),
(49, 2, 'Votre demande de congé du 2025-10-10 au 2025-10-11 a été approuvée par votre responsable.', '2025-10-10 12:31:10'),
(50, 2, 'Votre demande de congé du 2025-10-14 au 2025-10-16 a été approuvée par votre responsable.', '2025-10-10 12:44:41'),
(51, 2, 'Votre demande de congé du 2025-10-16 au 2025-10-18 a été approuvée par votre responsable.', '2025-10-10 13:24:55');

-- --------------------------------------------------------

--
-- Structure de la table `ordres_mission`
--

CREATE TABLE `ordres_mission` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date_mission` date NOT NULL,
  `heure_depart` time NOT NULL,
  `heure_arrivee` time NOT NULL,
  `destination` varchar(255) NOT NULL,
  `objet_mission` text NOT NULL,
  `moyens_transport` varchar(50) DEFAULT NULL,
  `frais_estimes` decimal(10,2) DEFAULT NULL,
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente',
  `motif_refus` text DEFAULT NULL,
  `date_demande` datetime DEFAULT current_timestamp(),
  `date_traitement` datetime DEFAULT NULL,
  `traite_par` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `ordres_mission`
--

INSERT INTO `ordres_mission` (`id`, `user_id`, `date_mission`, `heure_depart`, `heure_arrivee`, `destination`, `objet_mission`, `moyens_transport`, `frais_estimes`, `statut`, `motif_refus`, `date_demande`, `date_traitement`, `traite_par`) VALUES
(1, 2, '2025-10-04', '10:00:00', '11:00:00', 'tunis', 'aaaaaaaaaaaaaa', 'voiture', 10.00, 'approuve', NULL, '2025-10-03 23:32:44', '2025-10-03 23:56:56', 1);

-- --------------------------------------------------------

--
-- Structure de la table `pointages`
--

CREATE TABLE `pointages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('entrée','sortie') NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `pointages`
--

INSERT INTO `pointages` (`id`, `user_id`, `type`, `timestamp`) VALUES
(1, 2, 'entrée', '2025-08-22 14:18:43'),
(2, 2, 'sortie', '2025-08-22 14:18:45'),
(3, 2, 'entrée', '2025-08-22 14:26:07'),
(4, 2, 'sortie', '2025-08-22 14:28:19'),
(5, 2, 'entrée', '2025-08-22 14:28:20'),
(6, 2, 'sortie', '2025-08-22 14:28:21'),
(7, 2, 'entrée', '2025-08-22 14:28:22'),
(8, 2, 'sortie', '2025-08-22 14:28:23'),
(9, 2, 'entrée', '2025-08-22 14:28:50'),
(10, 2, 'sortie', '2025-08-22 14:30:02'),
(11, 2, 'entrée', '2025-08-22 14:32:24'),
(12, 2, 'sortie', '2025-08-22 15:08:20'),
(13, 2, 'entrée', '2025-09-03 19:01:28'),
(14, 2, 'sortie', '2025-09-03 19:01:33'),
(15, 2, 'entrée', '2025-09-03 19:12:58'),
(16, 2, 'sortie', '2025-09-03 19:13:03'),
(17, 2, 'entrée', '2025-09-04 15:56:51'),
(18, 2, 'sortie', '2025-09-04 16:00:23'),
(19, 2, 'entrée', '2025-09-04 16:16:24'),
(20, 2, 'sortie', '2025-09-04 16:16:25'),
(21, 2, 'entrée', '2025-09-04 16:17:12'),
(22, 2, 'sortie', '2025-09-04 16:17:19'),
(23, 2, 'entrée', '2025-09-04 16:18:06'),
(24, 2, 'sortie', '2025-09-04 16:18:16'),
(25, 2, 'entrée', '2025-09-04 23:26:20'),
(26, 2, 'sortie', '2025-09-04 23:26:28'),
(27, 2, 'entrée', '2025-09-08 18:47:51'),
(28, 2, 'sortie', '2025-09-08 18:47:54'),
(29, 2, 'entrée', '2025-09-11 10:14:51'),
(30, 2, 'sortie', '2025-09-11 10:14:54'),
(31, 2, 'entrée', '2025-09-11 10:20:48'),
(32, 2, 'sortie', '2025-09-11 10:20:52'),
(33, 2, 'entrée', '2025-09-14 18:43:46'),
(34, 2, 'sortie', '2025-09-14 18:45:02'),
(35, 5, 'entrée', '2025-09-15 10:55:32'),
(36, 5, 'sortie', '2025-09-15 10:57:05'),
(37, 5, 'sortie', '2025-09-15 11:01:19'),
(38, 7, 'entrée', '2025-09-16 11:07:49'),
(39, 7, 'sortie', '2025-09-16 11:07:54'),
(40, 6, 'entrée', '2025-09-16 11:08:25'),
(41, 6, 'sortie', '2025-09-16 11:08:28'),
(42, 2, 'entrée', '2025-09-19 10:48:53'),
(43, 2, 'sortie', '2025-09-19 10:49:04'),
(44, 7, 'entrée', '2025-09-21 22:53:21'),
(45, 7, 'sortie', '2025-09-21 22:53:24'),
(46, 7, 'entrée', '2025-09-29 21:02:33'),
(47, 7, 'sortie', '2025-09-29 21:03:29'),
(48, 12, 'entrée', '2025-09-29 22:04:38'),
(49, 12, 'sortie', '2025-09-29 22:04:40'),
(50, 5, '', '2025-10-01 16:52:08'),
(51, 5, '', '2025-10-04 18:35:57'),
(52, 5, '', '2025-10-04 18:38:58'),
(53, 5, '', '2025-10-05 13:01:15'),
(54, 5, '', '2025-10-05 13:04:04'),
(55, 5, '', '2025-10-05 13:04:34'),
(56, 5, '', '2025-10-05 13:19:21'),
(57, 5, '', '2025-10-05 13:20:16'),
(58, 14, 'entrée', '2025-10-06 10:49:59'),
(59, 14, 'sortie', '2025-10-06 10:50:09'),
(60, 13, '', '2025-10-06 11:21:45'),
(61, 5, '', '2025-10-07 12:35:15'),
(62, 5, '', '2025-10-07 14:52:27'),
(63, 5, '', '2025-10-07 15:08:24'),
(64, 5, '', '2025-10-09 14:09:47');

-- --------------------------------------------------------

--
-- Structure de la table `salaires_employes`
--

CREATE TABLE `salaires_employes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `salaire_mensuel` decimal(10,2) NOT NULL,
  `date_effet` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'company_name', 'PointagePro', 'Nom de l\'entreprise', '2025-09-11 09:58:40'),
(2, 'work_start_time', '08:00:00', 'Heure de début du travail', '2025-09-11 09:58:40'),
(3, 'work_end_time', '17:00:00', 'Heure de fin du travail', '2025-09-11 09:58:40'),
(4, 'max_early_arrival', '07:30:00', 'Arrivée anticipée maximale', '2025-09-11 09:58:40'),
(5, 'max_late_departure', '18:30:00', 'Départ tardif maximal', '2025-09-11 09:58:40'),
(6, 'allow_weekend_work', '0', 'Autoriser le travail le week-end', '2025-09-11 09:58:40');

-- --------------------------------------------------------

--
-- Structure de la table `soldes_conges`
--

CREATE TABLE `soldes_conges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  `mois` int(11) NOT NULL,
  `jours_acquis` decimal(3,1) DEFAULT 1.5,
  `jours_utilises` decimal(3,1) DEFAULT 0.0,
  `jours_restants` decimal(3,1) DEFAULT 1.5,
  `date_mise_a_jour` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `soldes_conges`
--

INSERT INTO `soldes_conges` (`id`, `user_id`, `annee`, `mois`, `jours_acquis`, `jours_utilises`, `jours_restants`, `date_mise_a_jour`) VALUES
(1, 2, 2024, 1, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(2, 2, 2024, 2, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(3, 2, 2024, 3, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(4, 2, 2024, 4, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(5, 2, 2024, 5, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(6, 2, 2024, 6, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(7, 2, 2024, 7, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(8, 2, 2024, 8, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(9, 2, 2024, 9, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(10, 2, 2024, 10, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(11, 2, 2024, 11, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(12, 2, 2024, 12, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(13, 2, 2025, 1, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(14, 2, 2025, 2, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(15, 2, 2025, 3, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(16, 2, 2025, 4, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(17, 2, 2025, 5, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(18, 2, 2025, 6, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(19, 2, 2025, 7, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(20, 2, 2025, 8, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(21, 2, 2025, 9, 1.5, 0.0, 1.5, '2025-10-10 12:23:36'),
(22, 2, 2025, 10, 1.5, 0.0, 1.5, '2025-10-10 12:23:36');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee','responsable','pdg') NOT NULL DEFAULT 'employee',
  `matiere` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_embauche` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `matricule`, `password`, `role`, `matiere`, `created_at`, `date_embauche`, `is_active`) VALUES
(1, 'rh', 'admin@gmail.com', NULL, '$2y$10$ME.rcd7vAeMAQT0KxISnQuXbI18geRMwNoQv9PgRfdHJHAIEcoSma', 'admin', '', '2025-08-22 13:10:15', NULL, 1),
(2, 'rania', 'rania@gmail.com', NULL, '$2y$10$GmUxeM6O0pFk6hqP3eykCONOA6e5qS9nHwA/.ChulLjbmz4d4YsW.', 'employee', '', '2025-08-22 13:14:01', '2024-01-01', 1),
(5, 'Responsable', 'responsable@gmail.com', NULL, '$2y$10$F2BWC1Bj6Qb/jtgeMkgD8OW5yAgPVnqeY.XYxzeYq4xCwQw260H9y', 'responsable', '', '2025-09-15 09:36:19', '2024-01-01', 1),
(6, 'oussema', 'jeljlioussema@gmail.com', NULL, '$2y$10$Uvj2V8Af8co9ibzuCRFiZOsIAQ.6koTa4VWjXSayOCpEDcLrd3YL6', 'responsable', 'Directeur qualité', '2025-09-16 09:47:15', NULL, 1),
(7, 'res_qualité', 'qualite@gmail.com', NULL, '$2y$10$54Wit7Xx5fwYWMaxTocPS.gTz9QCzFeNgem7UEf5YMwWfbfuyGtnO', 'employee', 'Qualité système', '2025-09-16 10:07:21', NULL, 1),
(9, 'PDG', 'pdg@gmail.com', NULL, '$2y$10$xYPBZjUVpgs/aPA54nWyX.SghuczDmLj.3nCIuX6YQ1WV4/kvmTI6', 'pdg', 'Direction Générale', '2025-09-27 17:15:02', NULL, 1),
(10, 'directeur', 'directeur@gmail.com', NULL, '$2y$10$sv35rrZ970uMP3QWpHzNdONSAB9wy9JO2c4XMFJeqH0IheE41653G', 'responsable', 'Directeur qualité', '2025-09-29 19:46:33', NULL, 1),
(11, 'produit', 'produit@gmail.com', NULL, '$2y$10$lfYxnzAxWZT4zyLeAJCUcevWWCcC7d8Ojt3D5wEclN4pWRkJGK70G', 'responsable', 'Directeur technique', '2025-09-29 19:48:28', NULL, 1),
(12, 'agent', 'agent@gmail.com', NULL, '$2y$10$0qJ5G36MnxOqqxd7KA/OZuchljIQbrRHPCvYlORJIMLo5J8iw03SC', 'employee', 'Agent de contrôle qualité', '2025-09-29 21:03:33', NULL, 1),
(13, 'amal', 'amal@gmail.com', NULL, '$2y$10$UZaMH30csOxtnYuKQuqPzemB4b8UyUWRNVVBWHfKD7veaix1Y3qrC', 'responsable', 'Directeur de système informatique', '2025-10-06 09:47:40', NULL, 1),
(14, 'tech', 'tech@gmail.com', NULL, '$2y$10$4w4/pV50ETUT69RfU2NbDe8FTHyvuho4TaTrvAGdGD18nE6cBJe8m', 'employee', 'Technicien informatique', '2025-10-06 09:49:20', '2024-01-01', 1);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `attestations_salaire`
--
ALTER TABLE `attestations_salaire`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `attestations_travail`
--
ALTER TABLE `attestations_travail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `autorisations`
--
ALTER TABLE `autorisations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `avances_salaire`
--
ALTER TABLE `avances_salaire`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `traite_par` (`traite_par`);

--
-- Index pour la table `conges`
--
ALTER TABLE `conges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `traite_par` (`traite_par`);

--
-- Index pour la table `conges_solde`
--
ALTER TABLE `conges_solde`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_mois_annee` (`user_id`,`annee`,`mois`);

--
-- Index pour la table `credits_salaire`
--
ALTER TABLE `credits_salaire`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `traite_par` (`traite_par`);

--
-- Index pour la table `demandes_recrutement`
--
ALTER TABLE `demandes_recrutement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `responsable_id` (`responsable_id`),
  ADD KEY `traite_par_pdg` (`traite_par_pdg`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Index pour la table `notes_service`
--
ALTER TABLE `notes_service`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auteur_id` (`auteur_id`);

--
-- Index pour la table `notes_service_lus`
--
ALTER TABLE `notes_service_lus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lecture` (`note_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `ordres_mission`
--
ALTER TABLE `ordres_mission`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `traite_par` (`traite_par`);

--
-- Index pour la table `pointages`
--
ALTER TABLE `pointages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Index pour la table `soldes_conges`
--
ALTER TABLE `soldes_conges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_mois_annee` (`user_id`,`annee`,`mois`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `matricule` (`matricule`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `attestations_salaire`
--
ALTER TABLE `attestations_salaire`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `attestations_travail`
--
ALTER TABLE `attestations_travail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `autorisations`
--
ALTER TABLE `autorisations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `avances_salaire`
--
ALTER TABLE `avances_salaire`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `conges`
--
ALTER TABLE `conges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT pour la table `conges_solde`
--
ALTER TABLE `conges_solde`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `credits_salaire`
--
ALTER TABLE `credits_salaire`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `demandes_recrutement`
--
ALTER TABLE `demandes_recrutement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `notes_service`
--
ALTER TABLE `notes_service`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `notes_service_lus`
--
ALTER TABLE `notes_service_lus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT pour la table `ordres_mission`
--
ALTER TABLE `ordres_mission`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `pointages`
--
ALTER TABLE `pointages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT pour la table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19567;

--
-- AUTO_INCREMENT pour la table `soldes_conges`
--
ALTER TABLE `soldes_conges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `attestations_salaire`
--
ALTER TABLE `attestations_salaire`
  ADD CONSTRAINT `attestations_salaire_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `attestations_travail`
--
ALTER TABLE `attestations_travail`
  ADD CONSTRAINT `attestations_travail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `autorisations`
--
ALTER TABLE `autorisations`
  ADD CONSTRAINT `autorisations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `avances_salaire`
--
ALTER TABLE `avances_salaire`
  ADD CONSTRAINT `avances_salaire_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `avances_salaire_ibfk_2` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `conges`
--
ALTER TABLE `conges`
  ADD CONSTRAINT `conges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `conges_ibfk_2` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `conges_solde`
--
ALTER TABLE `conges_solde`
  ADD CONSTRAINT `conges_solde_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `credits_salaire`
--
ALTER TABLE `credits_salaire`
  ADD CONSTRAINT `credits_salaire_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `credits_salaire_ibfk_2` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `demandes_recrutement`
--
ALTER TABLE `demandes_recrutement`
  ADD CONSTRAINT `demandes_recrutement_ibfk_1` FOREIGN KEY (`responsable_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `demandes_recrutement_ibfk_2` FOREIGN KEY (`traite_par_pdg`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_traite_par_pdg` FOREIGN KEY (`traite_par_pdg`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `notes_service`
--
ALTER TABLE `notes_service`
  ADD CONSTRAINT `notes_service_ibfk_1` FOREIGN KEY (`auteur_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `notes_service_lus`
--
ALTER TABLE `notes_service_lus`
  ADD CONSTRAINT `notes_service_lus_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `notes_service` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notes_service_lus_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `ordres_mission`
--
ALTER TABLE `ordres_mission`
  ADD CONSTRAINT `ordres_mission_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `ordres_mission_ibfk_2` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `pointages`
--
ALTER TABLE `pointages`
  ADD CONSTRAINT `pointages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `soldes_conges`
--
ALTER TABLE `soldes_conges`
  ADD CONSTRAINT `soldes_conges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
