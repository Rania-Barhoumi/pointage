-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 10 oct. 2025 à 15:09
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

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `conges`
--
ALTER TABLE `conges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `traite_par` (`traite_par`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `conges`
--
ALTER TABLE `conges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `conges`
--
ALTER TABLE `conges`
  ADD CONSTRAINT `conges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `conges_ibfk_2` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
