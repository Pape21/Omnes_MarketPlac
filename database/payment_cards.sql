--
-- Base de données: `omnes_marketplace`
--

-- --------------------------------------------------------

--
-- Structure de la table `payment_cards`
--

CREATE TABLE `payment_cards` (
  `id` int(11) NOT NULL,
  `card_type` varchar(50) NOT NULL,
  `card_number` varchar(20) NOT NULL,
  `card_name` varchar(100) NOT NULL,
  `expiry_date` varchar(5) NOT NULL,
  `cvv` varchar(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `payment_cards`
--

INSERT INTO `payment_cards` (`id`, `card_type`, `card_number`, `card_name`, `expiry_date`, `cvv`) VALUES
(1, 'visa', '4111111111111111', 'JOHN DOE', '12/25', '123'),
(2, 'mastercard', '5555555555554444', 'JANE SMITH', '10/24', '321'),
(3, 'amex', '378282246310005', 'ROBERT BROWN', '06/26', '4321');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `payment_cards`
--
ALTER TABLE `payment_cards`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `payment_cards`
--
ALTER TABLE `payment_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

