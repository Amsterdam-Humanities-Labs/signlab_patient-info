import gzip
import xml.etree.ElementTree as ET

class DutchWordNetParser:
    def __init__(self, xml_file):
        """Initialize the parser by loading the ODWN XML file."""
        self.xml_file = xml_file
        self.lemmas_dict = self._load_lemmas()

    def _load_lemmas(self):
        """Parse the XML file and store lemmas in a dictionary."""
        lemmas_dict = {}

        with gzip.open(self.xml_file, 'rt', encoding='utf-8') as file:
            tree = ET.parse(file)
            root = tree.getroot()

            # Find all lexical entries in the Lexicon
            for lex_entry in root.findall(".//LexicalEntry"):
                lemma_el = lex_entry.find("Lemma")
                if lemma_el is not None:
                    lemma = lemma_el.get("writtenForm")
                    pos = lemma_el.get("partOfSpeech")

                    # Store lemma with part of speech (POS)
                    lemmas_dict[lemma] = {"lemma": lemma, "pos": pos}

        return lemmas_dict

    def get_lemma(self, word):
        """Retrieve the base lemma for a given word."""
        return self.lemmas_dict.get(word, {"lemma": None, "pos": None})

# Initialize the parser with the ODWN XML file
odwn_parser = DutchWordNetParser("odwn_orbn_gwg-LMF_1.3.xml")

# Example: Find the lemma for "gekund"
word = "gekund"
result = odwn_parser.get_lemma(word)

print(f"Original Word: {word}")
print(f"Lemma: {result['lemma']}")
print(f"Part of Speech: {result['pos']}")
